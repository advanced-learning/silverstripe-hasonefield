<?php

class HasOneButtonField extends GridField
{
    /**
     * Helper function to add a HasOneButtonField to a tab, and have the added field be automatically added below
     * the related DropdownField (if found).
     * @param FieldList  $fields     The FieldList to add to.
     * @param string     $tab        The name of the Tab to add to.
     * @param string     $hasOneName Should be the name of the has_one e.g. 'Thing', NOT 'ThingID'.
     * @param DataObject $parent     The parent DataObject that has the has_one in question.
     * @return void
     * @throws Exception
     */
    public static function addFieldToTab(FieldList $fields, string $tab, string $hasOneName, DataObject $parent): void
    {
        $idField = "{$hasOneName}ID";

        if (!$parent->hasOneComponent($hasOneName)) {
            throw new Exception('$parent does not have given $hasOneName.');
        }

        $tabFieldList = $fields->fieldByName($tab)->Fields();
        $tabFields = $tabFieldList->dataFields();

        $fieldIndex = 0;
        $added = false;
        /**
         * @var string $fieldName
         * @var FormField $field
         */
        foreach ($tabFields as $fieldName => $field) {
            $matches = [];
            if (preg_match("/^(.+?)ID$/", $fieldName, $matches)) {
                $fieldNameLessId = $matches[1];
                if ($fieldNameLessId === $hasOneName) {
                    $relatedFieldSelector = "#Form_ItemEditForm_{$idField}_Holder";
                    $fields->addFieldToTab(
                        $tab,
                        LiteralField::create(
                            "{$hasOneName}_HasOneButtonFieldStyle",
                            "
                                <style>
                                    $relatedFieldSelector {
                                        border-bottom: 0;
                                    }

                                    $relatedFieldSelector.readonly + fieldset.hasonebutton[data-name='$hasOneName'] {
                                        height: 0;
                                        margin: 0;
                                        padding: 0;
                                        overflow: hidden;
                                        pointer-events: none;
                                    }
                                </style>
                            "
                        )
                    );

                    $nextKey = array_keys($tabFields)[$fieldIndex + 1] ?? '';
                    $fields->addFieldToTab($tab, new self($hasOneName, $hasOneName, $parent, $field), $nextKey);

                    $added = true;
                    break;
                }
            }

            $fieldIndex++;
        }

        if (!$added) {
            throw new Exception("Field was not added, as the related hasOne field ($idField) was not found.");
        }
    }

    protected $record;
    protected $parent;

    /**
     * Override create to do nothing.
     * @see HasOneButtonField::addFieldToTab instead.
     * @return void
     */
    public static function create(): void
    {
    }

    /**
     * Private as we don't want people directly creating these.
     * @see HasOneButtonField::addFieldToTab instead.
     * @param string         $name
     * @param string         $title
     * @param DataObject     $parent
     * @param FormField|null $relatedField
     */
    private function __construct($name, $title, $parent, FormField $relatedField = null)
    {
        $this->record = $parent->{$name}();
        $this->parent = $parent;
        $config = GridFieldConfig::create()
            ->addComponent(new GridFieldDetailForm())
            ->addComponent(new GridFieldHasOneEditButton($relatedField));
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
    private ?FormField $relatedField;

    public function __construct($relatedField)
    {
        $this->relatedField = $relatedField;
        parent::__construct();
    }

    public function getHTMLFragments($gridField)
    {
        if ($this->relatedField && $this->relatedField->isReadonly()) {
            return [];
        }

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

        switch ($this->relatedField ? get_class($this->relatedField) : '') {
            case DropdownField::class:
                $message = "Choose an existing <em>$objectName</em> from the above dropdown, or...<br />";
                break;
            case NumericField::class:
                $message = "Type the ID of an existing <em>$objectName</em> in the above field, or...<br />";
                break;
            default:
                $message = "Assign an existing <em>$objectName</em> above, or...<br />";
                break;
        }

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
