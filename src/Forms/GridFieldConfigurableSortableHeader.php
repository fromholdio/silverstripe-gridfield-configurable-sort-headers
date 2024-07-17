<?php

namespace Fromholdio\GridFieldConfigurableSortHeader\Forms;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_FormAction;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridState_Data;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DataObjectSchema;
use SilverStripe\ORM\SS_List;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class GridFieldConfigurableSortableHeader extends GridFieldSortableHeader
{
    protected array $excludedColumns = [];

    protected bool $excludeAllColumns = false;


    public function setExcludedColumns(array $columns): self
    {
        $this->excludedColumns = $columns;
        return $this;
    }

    public function getExcludedColumns(): array
    {
        return $this->excludedColumns;
    }

    public function setExcludeAllColumns(bool $doExclude): self
    {
        $this->excludeAllColumns = $doExclude;
        return $this;
    }

    public function getExcludeAllColumns(): bool
    {
        return $this->excludeAllColumns;
    }


    public function getHTMLFragments($gridField)
    {
        $list = $gridField->getList();
        if (!$this->checkDataType($list)) {
            return null;
        }
        $forTemplate = new ArrayData([]);
        $forTemplate->Fields = new ArrayList;

        $state = $this->getState($gridField);
        $columns = $gridField->getColumns();
        $currentColumn = 0;

        $schema = DataObject::getSchema();
        foreach ($columns as $columnField) {
            $currentColumn++;
            $metadata = $gridField->getColumnMetadata($columnField);
            $fieldName = str_replace('.', '-', $columnField ?? '');
            $title = $metadata['title'];

            if (isset($this->fieldSorting[$columnField]) && $this->fieldSorting[$columnField]) {
                $columnField = $this->fieldSorting[$columnField];
            }

            $doExcludeAll = $this->getExcludeAllColumns();
            $excludedColumns = $this->getExcludedColumns();
            if ($doExcludeAll || in_array($columnField, $excludedColumns)) {
                $allowSort = false;
            }
            else {
                $allowSort = ($title && $list->canSortBy($columnField));

                if (!$allowSort && strpos($columnField ?? '', '.') !== false) {
                    // we have a relation column with dot notation
                    // @see DataObject::relField for approximation
                    $parts = explode('.', $columnField ?? '');
                    $tmpItem = singleton($list->dataClass());
                    for ($idx = 0; $idx < sizeof($parts ?? []); $idx++) {
                        $methodName = $parts[$idx];
                        if ($tmpItem instanceof SS_List) {
                            // It's impossible to sort on a HasManyList/ManyManyList
                            break;
                        } elseif ($tmpItem && ClassInfo::hasMethod($tmpItem, $methodName)) {
                            // The part is a relation name, so get the object/list from it
                            $tmpItem = $tmpItem->$methodName();
                        } elseif ($tmpItem instanceof DataObject
                            && $schema->fieldSpec($tmpItem, $methodName, DataObjectSchema::DB_ONLY)
                        ) {
                            // Else, if we've found a database field at the end of the chain, we can sort on it.
                            // If a method is applied further to this field (E.g. 'Cost.Currency') then don't try to sort.
                            $allowSort = $idx === sizeof($parts ?? []) - 1;
                            break;
                        } else {
                            // If neither method nor field, then unable to sort
                            break;
                        }
                    }
                }
            }

            if ($allowSort) {
                $dir = 'asc';
                if ($state->SortColumn(null) == $columnField && $state->SortDirection('asc') == 'asc') {
                    $dir = 'desc';
                }

                $field = GridField_FormAction::create(
                    $gridField,
                    'SetOrder' . $fieldName,
                    $title,
                    "sort$dir",
                    ['SortColumn' => $columnField]
                )->addExtraClass('grid-field__sort');

                if ($state->SortColumn(null) == $columnField) {
                    $field->addExtraClass('ss-gridfield-sorted');

                    if ($state->SortDirection('asc') == 'asc') {
                        $field->addExtraClass('ss-gridfield-sorted-asc');
                    } else {
                        $field->addExtraClass('ss-gridfield-sorted-desc');
                    }
                }
            } else {
                $field = new LiteralField($fieldName, '<span class="non-sortable">' . $title . '</span>');
            }
            $forTemplate->Fields->push($field);
        }

        $template = SSViewer::get_templates_by_class(parent::class, '_Row', __CLASS__);
        return [
            'header' => $forTemplate->renderWith($template),
        ];
    }


    /**
    * Extract state data from the parent gridfield
    * @param GridField $gridField
    * @return GridState_Data
    */
    private function getState(GridField $gridField): GridState_Data
    {
        return $gridField->State->GridFieldSortableHeader;
    }
}
