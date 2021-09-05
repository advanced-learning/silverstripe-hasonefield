<?php

class HasOneButtonField extends GridField
{
    /**
     * Helper function to add a HasOneButtonField to a tab, and have the added field be automatically added below
     * the related DropdownField (if found).
     * @param FieldList  $fields
     * @param string     $tab
     * @param string     $name
     * @param DataObject $parent
     * @return void
     */
    public static function addFieldToTab(FieldList $fields, string $tab, string $name, DataObject $parent): void
    {
        $fields->addFieldToTab($tab, self::create($name, $name, $parent));

        $tabFieldList = $fields->fieldByName($tab)->Fields();
        $tabFields = $tabFieldList->dataFields();
        $hasOneButtonFields = [];
        foreach ($tabFields as $fieldName => $field) {
            if ($field->class === __CLASS__) {
                $hasOneButtonFields[] = $fieldName;
            }
        }
        $addedHasOneButtonFields = [];
        $newFieldOrder = [];

        /**
         * @var string $fieldName
         * @var FormField $field
         */
        foreach ($tabFields as $fieldName => $field) {
            if (in_array($fieldName, $addedHasOneButtonFields, true)) {
                // Hide the border between the two related fields.
                try {
                    $existingStyle = $field->getAttribute('style');
                } catch (Throwable $e) {
                    $existingStyle = '';
                }
                $field->setAttribute('style', 'background: #f5f7f8; margin-top: -10px; ' . $existingStyle);
                continue;
            }
            $matches = [];
            if (preg_match("/^(.+?)ID$/", $fieldName, $matches)) {
                $fieldNameLessId = $matches[1];
                if (in_array($fieldNameLessId, $hasOneButtonFields, true)) {
                    $newFieldOrder[] = $fieldName;
                    $newFieldOrder[] = $fieldNameLessId;
                    $addedHasOneButtonFields[] = $fieldNameLessId;
                    continue;
                }
            }

            $newFieldOrder[] = $fieldName;
        }

        $tabFieldList->changeFieldOrder($newFieldOrder);
    }

    protected $record;
    protected $parent;

    public function __construct($name, $title, $parent)
    {
        $this->record = $parent->{$name}();
        $this->parent = $parent;
        $config = GridFieldConfig::create()
            ->addComponent(new GridFieldDetailForm())
            ->addComponent(new GridFieldHasOneEditButton());
        $list = new HasOneButtonRelationList($this->record, $name, $parent);
        parent::__construct($name, $title, $list, $config);
    }

    public function getRecord()
    {
        return $this->record;
    }
}

class GridFieldHasOneEditButton extends GridFieldAddNewButton implements GridField_HTMLProvider
{

    public function getHTMLFragments($gridField)
    {
        $singleton = singleton($gridField->getModelClass());
        if (!$singleton->canCreate()) {
            return [];
        }

        $record = $gridField->getRecord();
        $recordExists = $record->exists() && $record->isInDB();

        $objectName = $singleton->i18n_singular_name();

        $editButtonData = $recordExists ? new ArrayData([
            'NewLink' => Controller::join_links($gridField->Link('item'), $record->ID, 'edit'),
            'ButtonName' => 'Edit existing',
        ]) : null;

        $newButtonData = new ArrayData([
            'NewLink' => Controller::join_links($gridField->Link('item'), 'new'),
            'ButtonName' => $recordExists ? 'Unlink existing and add new' : 'Create and link new'
        ]);

        $fieldIsDropdown = $gridField->getList()->count() < 100;
        $message = $fieldIsDropdown ?
            "Choose an existing <em>$objectName</em> from the above dropdown, or...<br />" :
            "Type the ID of an existing <em>$objectName</em> in the above field, or...<br />";

        $fragments = [
            'before' => $message,
            'after' => $newButtonData->renderWith('GridFieldAddNewbutton'),
        ];

        if ($editButtonData) {
            $fragments['before'] .= $editButtonData->renderWith('GridFieldAddNewbutton');
        }

        return $fragments;
    }
}

class HasOneButtonRelationList extends DataList
{

    protected $record;
    protected $name;
    protected $parent;

    public function __construct($record, $name, $parent)
    {
        $this->record = $record;
        $this->name = $name;
        $this->parent = $parent;
        parent::__construct($record->ClassName);
    }

    public function add($item)
    {
        $this->parent->{$this->name."ID"} = $item->ID;
        $this->parent->write();
    }
}
