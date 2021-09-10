# SilverStripe has_one field

Allows you to create a CMS button for creating and editing a single related object. It is actually a grid field, but just looks like a button.

Note that this demo gif is not a perfect representation of our fork, but gets the point across.

![demo](https://raw.github.com/wiki/burnbright/silverstripe-hasonefield/images/hasonefield.gif)

## Usage

```php
    public function getCMSFields() {
        $fields = parent::getCMSFields();

        // The buttons will be automatically added below the "AddressID" field.
        HasOneButtonField::addFieldToTab($fields, "Root.Main", "Address", $this);

        return $fields;
    }
```

You must pass through the parent context ($this), so that the has_one relationship can be set by the `GridFieldDetailForm`.

## Caveats

The field name must match the has_one relationship name.