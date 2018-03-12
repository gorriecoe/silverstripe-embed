<?php

namespace gorriecoe\Embed\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Core\Convert;
use SilverStripe\Assets\Folder;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Member;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\View\SSViewer;
use SilverStripe\ORM\DataExtension;
use Embed\Embed;

/**
 * Embeddable
 *
 * @package silverstripe
 * @subpackage mysite
 */
class Embeddable extends DataExtension
{
    /**
     * Database fields
     * @var array
     */
    private static $db = array(
        'EmbedTitle' => 'Varchar(255)',
        'EmbedType' => 'Varchar',
        'EmbedSourceURL' => 'Varchar(255)',
        'EmbedSourceImageURL' => 'Varchar(255)',
        'EmbedHTML' => 'HTMLText',
        'EmbedWidth' => 'Varchar',
        'EmbedHeight' => 'Varchar',
        'EmbedAspectRatio' => 'Varchar',
        'EmbedDescription' => 'HTMLText'
    );

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = array(
        'EmbedImage' => Image::class
    );

    /**
     * List the allowed included embed types.  If null all are allowed.
     * @var array
     */
    private static $allowed_embed_types = null;

    /**
     * Defines tab to insert the embed fields into.
     * @var string
     */
    private static $embed_tab = 'Main';

    /**
     * List of custom CSS classes for template.
     * @var array
     */
    protected $classes = array();

    /**
     * Defines the template to render the embed in.
     * @var string
     */
    protected $template = 'Embed';

    /**
     * Update Fields
     * @return FieldList
     */
    public function updateCMSFields(FieldList $fields)
    {
        $owner = $this->owner;
        $tab = $owner->config()->get('embed_tab');
        $tab = isset($tab) ? $tab : 'Main';

        // Ensure these fields don't get added by fields scaffold
        $fields->removeByName(array(
            'EmbedTitle',
            'EmbedType',
            'EmbedSourceURL',
            'EmbedSourceImageURL',
            'EmbedHTML',
            'EmbedWidth',
            'EmbedHeight',
            'EmbedAspectRatio',
            'EmbedDescription',
            'EmbedImage'
        ));

        $fields->addFieldsToTab(
            'Root.' . $tab,
            array(
                TextField::create(
                    'EmbedTitle',
                    _t(__CLASS__ . '.TITLE.LABEL', 'Title')
                )
                ->setDescription(
                    _t(__CLASS__ . '.TITLE.DESCRIPTION', 'Optional. Will be auto-generated if left blank')
                ),
                TextField::create(
                    'EmbedSourceURL',
                    _t(__CLASS__ . '.SOURCEURL.LABEL', 'Source URL')
                )
                ->setDescription(
                    _t(__CLASS__ . '.SOURCEURL.DESCRIPTION', 'Specify a external URL')
                ),
                UploadField::create(
                    'EmbedImage',
                    _t(__CLASS__ . '.IMAGE.LABEL', 'Image')
                )
                ->setFolderName($owner->EmbedFolder)
                ->setAllowedExtensions(array('jpg','png','gif')),
                TextareaField::create(
                    'EmbedDescription',
                    _t(__CLASS__ . '.DESCRIPTION.LABEL', 'Description')
                )
            )
        );

        if (Count($this->AllowedEmbedTypes) > 1) {
            $fields->addFieldToTab(
                'Root.' . $tab,
                ReadonlyField::create(
                    'EmbedType',
                    _t(__CLASS__ . '.TYPE.LABEL', 'Type')
                ),
                'EmbedImage'
            );
        }

        return $fields;
    }

    /**
     * Event handler called before writing to the database.
     */
    public function onBeforeWrite()
    {
        $owner = $this->owner;
        $changes = $owner->getChangedFields();
        if ($sourceURL = $owner->EmbedSourceURL) {
            $embed = Embed::create($sourceURL);
            if ($owner->EmbedTitle == '') {
                $owner->EmbedTitle = $embed->getTitle();
            }
            if (!$owner->EmbedDescription == '') {
                $owner->EmbedDescription = $embed->getDescription();
            }
            if (isset($changes['EmbedSourceURL']) && !$owner->EmbedImageID) {
                $owner->EmbedHTML = $embed->getCode();
                $owner->EmbedType = $embed->getType();
                $owner->EmbedWidth = $embed->getWidth();
                $owner->EmbedHeight = $embed->getHeight();
                $owner->EmbedAspectRatio = $embed->getAspectRatio();
                if ($owner->EmbedSourceImageURL != $embed->getImage()) {
                    $owner->EmbedSourceImageURL = $embed->getImage();
                    $fileExplode = explode('.', $embed->getImage());
                    $fileExtension = end($fileExplode);
                    $fileName = Convert::raw2url($owner->obj('EmbedTitle')->LimitCharacters(55)) . '.' . $fileExtension;
                    $parentFolder = Folder::find_or_make($owner->EmbedFolder);

                    // Save image to server
                    $tmpFileContent = file_get_contents($embed->getImage());
                    file_put_contents($parentFolder->FullPath . '/' . $fileName, $tmpFileContent);

                    // Check existing for image object or create new
                    $imageObject = DataObject::get_one(
                        Image::class,
                        array(
                            'Name' => $fileName,
                            'ParentID' => $parentFolder->ID
                        )
                    );
                    if(!$imageObject){
                        $imageObject = Image::create();
                    }
                    $imageObject->ParentID = $parentFolder->ID;
                    $imageObject->Name = $fileName;
                    $imageObject->Title = $embed->getTitle();
                    $imageObject->OwnerID = (Member::currentUserID() ? Member::currentUserID() : 0);
                    $imageObject->ShowInSearch = false;
                    $imageObject->write();

                    $owner->EmbedImageID = $imageObject->ID;
                }
            }
        }
    }

    public function getAllowedEmbedTypes()
    {
        return $owner->config()->get('allowed_embed_types');
    }

    public function validate(ValidationResult $validationResult)
    {
        $owner = $this->owner;
        $allowed_types = $this->AllowedEmbedTypes;
        if ($sourceURL = $owner->SourceURL && isset($allowed_types)) {
            $embed = Embed::create($sourceURL);
            if (!in_array($embed->getType(), $allowed_types)) {
                $string = implode(', ', $allowed_types);
                $string = (substr($string, -1) == ',') ? substr_replace($string, ' or', -1) : $string;
                $validationResult->error(
                    _t(__CLASS__ . '.ERROR.NOTSTRING', "The embed content is not a $string")
                );
            }
        }

        return $validationResult;
    }

    public function getEmbedFolder()
    {
        $owner = $this->owner;
        $folder = $owner->config()->get('embed_folder');
        if (!isset($folder)) {
            $folder = $owner->ClassName;
        }
        return $folder;
    }

    /**
     * Set CSS classes for templates
     * @param string $class CSS classes.
     * @return DataObject Owner
     */
    public function setEmbedClass($class)
    {
        $classes = ($class) ? explode(' ', $class) : [];
        foreach ($classes as $key => $value) {
            $this->classes[$value] = $value;
        }
        return $this->owner;
    }

    /**
     * Returns the classes for this embed.
     * @return string
     */
    public function getEmbedClass()
    {
        $classes = $this->classes;
        if (Count($classes)) {
            return implode(' ', $classes);
        }
    }

    /**
     * Set CSS classes for templates
     * @param string $class CSS classes.
     * @return DataObject Owner
     */
    public function setEmbedTemplate($template)
    {
        if (isset($template)) {
            $this->template = $template;
        }
        return $this->owner;
    }

    /**
     * Renders embed into appropriate template HTML
     * @return HTML
     */
    public function getEmbed()
    {
        $owner = $this->owner;
        $title = $owner->EmbedTitle;
        $class = $owner->EmbedClass;
        $type = $owner->EmbedType;
        $template = $this->template;
        $embedHTML = $owner->EmbedHTML;
        $sourceURL = $owner->EmbedSourceURL;
        $templates = array();
        if ($type) {
            $templates[] = $template . '_' . $type;
        }
        $templates[] = $template;
        if (SSViewer::hasTemplate($templates)) {
            return $owner->renderWith($templates);
        }
        switch ($type) {
            case 'video':
            case 'rich':
                return "<div class='$class'>$embedHTML</div>";
                break;
            case 'link':
                return "<a class='$class' href='$sourceURL'>$title</a>";
                break;
            case 'photo':
                return "<img src='$sourceURL' width='$this->Width' height='$this->Height' class='$class' alt='$title' />";
                break;
        }
    }
}
