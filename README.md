# Silverstripe embed
Adds embed and video a dataobject along with dataextension to apply embed to existing objects.

## Installation
Composer is the recommended way of installing SilverStripe modules.
```
composer require gorriecoe/silverstripe-embed
```

## Requirements

- silverstripe/framework ^4.0

## Maintainers

- [Gorrie Coe](https://github.com/gorriecoe)

## Usage
Relationship to Embed Dataobjects
```php
use gorriecoe\Embed\Models\Embed;

class ClassName extends DataObject
{
    private static $has_one = [
        'Embed' => Embed::class,
        'Video' => Video::class
    ];

    public function getCMSFields()
    {
        ...
        $fields->addFieldsToTab(
            'Main',
            [
                HasOneButtonField::create(
                    'Embed',
                    'Embed',
                    $this
                ),
                HasOneButtonField::create(
                    'Video',
                    'Video',
                    $this
                )
            ]
        );
        ...
    }
}

```
Update current DataObject to be Embeddable with DataExtension
```php
use gorriecoe\Embed\Extensions\Embeddable;

class ClassName extends DataObject
{
    private static $extensions = [
        Embeddable::class,
    ];

    /**
     * List the allowed included embed types.  If null all are allowed.
     * @var array
     */
    private static $allowed_embed_types = [
        'video',
        'photo'
    ];

    /**
     * Defines tab to insert the embed fields into.
     * @var string
     */
    private static $embed_tab = 'Main';
}

```
