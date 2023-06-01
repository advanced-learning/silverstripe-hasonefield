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
                    $fieldsetSelector = "fieldset.hasonebutton[data-name='$hasOneName']";

                    $styleKey = "{$hasOneName}_HasOneButtonFieldStyle";
                    $nextKey = array_keys($tabFields)[$fieldIndex + 1] ?? '';

                    $fields->addFieldToTab(
                        $tab,
                        LiteralField::create(
                            $styleKey,
                            "
                                <style>
                                    $relatedFieldSelector {
                                        border-bottom: 0;
                                    }

                                    $relatedFieldSelector.readonly + $fieldsetSelector > :not(a[href$=edit]) {
                                        display: none;
                                    }

                                    $fieldsetSelector > div {
                                        margin-bottom: 5px;
                                    }
                                </style>
                            "
                        ),
                        $nextKey
                    );

                    $fields->addFieldToTab($tab, new self($hasOneName, $hasOneName, $parent, $field), $styleKey);

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
        $list = $list->filter('ID', $this->record->ID);
        parent::__construct($name, $title, $list, $config);
    }

    public function getRecord()
    {
        return $this->record;
    }

    /**
     * This field will hide the "new"/"unlink" button if the related field is readonly.
     * @return $this
     */
    public function performReadonlyTransformation()
    {
        return $this;
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

        $viewEditButtonData = $recordExists ? new ArrayData([
            'NewLink' => Controller::join_links($gridField->Link('item'), $record->ID, 'edit'),
            'ButtonName' => 'View/edit existing',
        ]) : null;

        $newButtonData = new ArrayData([
            'NewLink' => Controller::join_links($gridField->Link('item'), 'new'),
            'ButtonName' => $recordExists ? 'Unlink existing and add new' : 'Create and link new'
        ]);

        switch ($this->relatedField ? get_class($this->relatedField) : '') {
            case DropdownField::class:
                $message = "<div>Choose an existing <em>$objectName</em> from the above dropdown, or...</div>";
                break;
            case NumericField::class:
                $message = "<div>Type the ID of an existing <em>$objectName</em> in the above field, or...</div>";
                break;
            default:
                $message = "<div>Assign an existing <em>$objectName</em> above, or...</div>";
                break;
        }

        $fragments = [
            'before' => $message,
            'after' => $newButtonData->renderWith('GridFieldAddNewButton'),
        ];

        if ($viewEditButtonData) {
            $fragments['before'] .= $viewEditButtonData->renderWith('GridFieldAddNewButton');
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
