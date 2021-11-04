<?php

namespace gorriecoe\Embed\Extensions;

use SilverStripe\Assets\Image;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\AssetAdmin\Forms\UploadField;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationResult;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\SSViewer;
use SilverStripe\Security\Member;
use Embed\Embed;
use gorriecoe\HTMLTag\View\HTMLTag;

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
    private static $db = [
        'EmbedTitle' => 'Varchar(255)',
        'EmbedType' => 'Varchar',
        'EmbedSourceURL' => 'Varchar(255)',
        'EmbedSourceImageURL' => 'Varchar(255)',
        'EmbedHTML' => 'HTMLText',
        'EmbedWidth' => 'Varchar',
        'EmbedHeight' => 'Varchar',
        'EmbedAspectRatio' => 'Varchar',
        'EmbedDescription' => 'HTMLText'
    ];

    /**
     * Has_one relationship
     * @var array
     */
    private static $has_one = [
        'EmbedImage' => Image::class
    ];

    /**
     * Relationship version ownership
     * @var array
     */
    private static $owns = [
        'EmbedImage'
    ];

    /**
     * Defines tab to insert the embed fields into.
     * @var string
     */
    private static $embed_tab = 'Main';

    /**
     * List of custom CSS classes for template.
     * @var array
     */
    protected $classes = [];

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
        $fields->removeByName([
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
        ]);

        $fields->addFieldsToTab(
            'Root.' . $tab,
            array(
                TextField::create(
                    'EmbedTitle',
                    _t(__CLASS__ . '.TITLELABEL', 'Title')
                )
                ->setDescription(
                    _t(__CLASS__ . '.TITLEDESCRIPTION', 'Optional. Will be auto-generated if left blank')
                ),
                TextField::create(
                    'EmbedSourceURL',
                    _t(__CLASS__ . '.SOURCEURLLABEL', 'Source URL')
                )
                ->setDescription(
                    _t(__CLASS__ . '.SOURCEURLDESCRIPTION', 'Specify a external URL')
                ),
                UploadField::create(
                    'EmbedImage',
                    _t(__CLASS__ . '.IMAGELABEL', 'Image')
                )
                ->setFolderName($owner->EmbedFolder)
                ->setAllowedExtensions(['jpg','png','gif']),
                TextareaField::create(
                    'EmbedDescription',
                    _t(__CLASS__ . '.DESCRIPTIONLABEL', 'Description')
                )
            )
        );

        if (isset($owner->AllowedEmbedTypes) && is_array($owner->AllowedEmbedTypes) && Count($owner->AllowedEmbedTypes) > 1) {
            $fields->addFieldToTab(
                'Root.' . $tab,
                ReadonlyField::create(
                    'EmbedType',
                    _t(__CLASS__ . '.TYPELABEL', 'Type')
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
        if ($sourceURL = $owner->EmbedSourceURL) {
            $embed = Embed::create($sourceURL);
            if ($owner->EmbedTitle == '') {
                $owner->EmbedTitle = $embed->Title;
            }
            if (!$owner->EmbedDescription == '') {
                $owner->EmbedDescription = $embed->Description;
            }
            $changes = $owner->getChangedFields();
            if (isset($changes['EmbedSourceURL']) && !$owner->EmbedImageID) {
                $owner->EmbedHTML = $embed->Code;
                $owner->EmbedType = $embed->Type;
                $owner->EmbedWidth = $embed->Width;
                $owner->EmbedHeight = $embed->Height;
                $owner->EmbedAspectRatio = $embed->AspectRatio;
                if ($owner->EmbedSourceImageURL != $embed->Image) {
                    $owner->EmbedSourceImageURL = $embed->Image;
                    $fileExplode = explode('.', $embed->Image);
                    $fileExtension = end($fileExplode);
                    $fileName = Convert::raw2url($owner->obj('EmbedTitle')->LimitCharacters(55)) . '.' . $fileExtension;
                    $parentFolder = Folder::find_or_make($owner->EmbedFolder);

                    $imageObject = DataObject::get_one(
                        Image::class,
                        [
                            'Name' => $fileName,
                            'ParentID' => $parentFolder->ID
                        ]
                    );
                    if(!$imageObject){
                        // Save image to server
                        $imageObject = Image::create();
                        $imageObject->setFromString(
                            file_get_contents($embed->Image),
                            $owner->EmbedFolder . '/' . $fileName,
                            null,
                            null,
                            [
                                'conflict' => AssetStore::CONFLICT_OVERWRITE
                            ]
                        );
                    }

                    // Check existing for image object or create new
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

    /**
     * @return array()|null
     */
    public function getAllowedEmbedTypes()
    {
        return $this->owner->config()->get('allowed_embed_types');
    }

    /**
     * @param  ValidationResult $validationResult
     * @return ValidationResult
     */
    public function validate(ValidationResult $validationResult)
    {
        $owner = $this->owner;
        $allowed_types = $owner->AllowedEmbedTypes;
        $sourceURL = $owner->EmbedSourceURL;
        if ($sourceURL && isset($allowed_types)) {
            $embed = Embed::create($sourceURL);
            if (!in_array($embed->Type, $allowed_types)) {
                $string = implode(', ', $allowed_types);
                $string = (substr($string, -1) == ',') ? substr_replace($string, ' or', -1) : $string;
                $validationResult->addError(
                    _t(__CLASS__ . '.ERRORNOTSTRING', "The embed content is not a $string")
                );
            }
        }
        return $validationResult;
    }

    /**
     * @return string
     */
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
        $templates = [];
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
                $html = HTMLTag::create($embedHTML, 'div');
                break;
            case 'link':
                $html = HTMLTag::create($title, 'a')->setAttribute('href', $sourceURL);
                break;
            case 'photo':
                $html = HTMLTag::create($sourceURL, 'img')->setAttribute([
                    'width' => $this->Width,
                    'height' => $this->Height,
                    'alt' => $title
                ]);
                break;
        }
        return $html->setClass($class);
    }
}
