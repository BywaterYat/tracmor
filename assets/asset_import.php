<?php
/*
 * Copyright (c)  2009, Tracmor, LLC
 *
 * This file is part of Tracmor.
 *
 * Tracmor is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tracmor is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tracmor; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// Include prepend.inc to load Qcodo
require('../includes/prepend.inc.php'); /* if you DO NOT have "includes/" in your include_path */
QApplication::Authenticate(2);
require(__DOCROOT__ . __PHP_ASSETS__ . '/csv/DataSource.php');

class AssetImportForm extends QForm
{
    // Header Menu
    protected $ctlHeaderMenu;

    protected $pnlMain;
    protected $pnlStepOne;
    protected $pnlStepTwo;
    protected $pnlStepThree;
    protected $lstFieldSeparator;
    protected $txtFieldSeparator;
    protected $lstTextDelimiter;
    protected $txtTextDelimiter;
    protected $lstImportAction;
    protected $flcFileCsv;
    protected $FileCsvData;
    protected $arrCsvHeader;
    protected $arrMapFields;
    protected $strFilePathArray;
    protected $lstMapHeaderArray;
    protected $txtMapDefaultValueArray;
    protected $lstMapDefaultValueArray;
    protected $dtpDateArray;
    protected $strAcceptibleMimeArray;
    protected $chkHeaderRow;
    protected $blnHeaderRow;
    protected $btnNext;
    protected $btnCancel;
    protected $intStep;
    protected $arrAssetCustomField;
    protected $intItemIdKey;
    protected $arrTracmorField;
    protected $dtgAsset;
    protected $objNewAssetArray;
    protected $dtgUpdatedAsset;
    protected $objUpdatedAssetArray;
    protected $blnImportEnd;
    protected $intImportStep;
    protected $intLocationKey;
    protected $intCategoryKey;
    protected $intManufacturerKey;
    protected $intCreatedByKey;
    protected $intCreatedDateKey;
    protected $intModifiedByKey;
    protected $intModifiedDateKey;
    protected $intUserArray;
    protected $lblImportSuccess;
    protected $intSkippedRecordCount;
    protected $strFilePath;
    protected $btnUndoLastImport;
    protected $btnImportMore;
    protected $btnReturnToAssets;
    protected $objDatabase;
    protected $btnRemoveArray;
    protected $intTotalCount;
    protected $intCurrentFile;
    protected $strSelectedValueArray;
    protected $strAssetValuesArray;
    protected $intAssetModelArray;
    protected $lblImportResults;
    protected $lblImportAssets;
    protected $lblImportUpdatedAssets;
    protected $lblImportAsset;
    protected $arrOldItemArray;
    protected $objUpdatedItemArray;
    protected $blnError;

    protected function Form_Create()
    {
        if (QApplication::QueryString('intDownloadCsv')) {
            $this->RenderBegin(false);

            session_cache_limiter('must-revalidate'); // force a "no cache" effect
            header("Pragma: hack"); // IE chokes on "no cache", so set to something, anything, else.
            $ExpStr = "Expires: " . gmdate("D, d M Y H:i:s", time()) . " GMT";
            header($ExpStr);
            header('Content-Type: text/csv');
            header('Content-Disposition: csv; filename=skipped_records.csv');

            $file = fopen(sprintf("%s%s/%s_skipped.csv", __DOCROOT__ . __SUBDIRECTORY__, __TRACMOR_TMP__, $_SESSION['intUserAccountId']), "r");
            ob_end_clean();
            while ($row = fgets($file, 1000)) {
                print $row;
                @ob_flush();
                flush();
            }

            QApplication::$JavaScriptArray = array();
            QApplication::$JavaScriptArrayHighPriority = array();
            $this->RenderEnd(false);
            exit();
        }
        // Create the Header Menu
        $this->ctlHeaderMenu_Create();
        $intRoleId = QApplication::$objUserAccount->RoleId;
        $this->blnError = true;
        $objRoleEntityQtypeBuiltInAuthorization = RoleEntityQtypeBuiltInAuthorization::LoadByRoleIdEntityQtypeIdAuthorizationId($intRoleId, EntityQtype::Asset, 2);
        // Check the user have edit permissions
        if ($objRoleEntityQtypeBuiltInAuthorization && $objRoleEntityQtypeBuiltInAuthorization->AuthorizedFlag) {
            $this->blnError = false;
        }
        if (!$this->blnError) {
            $this->pnlMain_Create();
            $this->pnlStepOne_Create();
            $this->Buttons_Create();
            $this->intStep = 1;
            $this->intSkippedRecordCount = 0;
            $this->blnImportEnd = true;
            $this->btnRemoveArray = array();
            $this->Labels_Create();
            $this->objDatabase = Asset::GetDatabase();
            $this->intItemIdKey = null;
            $this->objUpdatedItemArray = array();

            $this->arrAssetCustomField = array();
            $intCustomFieldIdArray = array();
            // Load Asset Model Custom Field
            foreach (CustomField::LoadArrayByActiveFlagEntity(1, EntityQtype::Asset) as $objCustomField) {
                $this->arrAssetCustomField[$objCustomField->CustomFieldId] = $objCustomField;
                $intCustomFieldIdArray[] = $objCustomField->CustomFieldId;
            }
            if (count($intCustomFieldIdArray)) {
                //QApplication::$Database[1]->EnableProfiling();
                // Load restrict permisions for Asset Cutom Fields
                $objConditions = QQ::AndCondition(
                    QQ::Equal(QQN::RoleEntityQtypeCustomFieldAuthorization()->RoleId, (string)$intRoleId),
                    QQ::In(QQN::RoleEntityQtypeCustomFieldAuthorization()->EntityQtypeCustomField->CustomFieldId, $intCustomFieldIdArray),
                    QQ::Equal(QQN::RoleEntityQtypeCustomFieldAuthorization()->AuthorizedFlag, false)
                );
                $objClauses = array();
                array_push($objClauses, QQ::Expand(QQN::RoleEntityQtypeCustomFieldAuthorization()->EntityQtypeCustomField->EntityQtypeCustomFieldId));
                array_push($objClauses, QQ::OrderBy(QQN::RoleEntityQtypeCustomFieldAuthorization()->EntityQtypeCustomFieldId));
                $arrRoleEntityQtypeCustomFieldAuthorization = RoleEntityQtypeCustomFieldAuthorization::QueryArray($objConditions, $objClauses);
                if ($arrRoleEntityQtypeCustomFieldAuthorization) foreach ($arrRoleEntityQtypeCustomFieldAuthorization as $objRoleAuth) {
                    if (array_key_exists($objRoleAuth->EntityQtypeCustomField->CustomFieldId, $this->arrAssetCustomField)) {
                        unset($this->arrAssetCustomField[$objRoleAuth->EntityQtypeCustomField->CustomFieldId]);
                    }
                }
                //QApplication::$Database[1]->OutputProfiling();
            }
            $this->intUserArray = array();
            // Load Users
            /*foreach (UserAccount::LoadAll() as $objUser) {
              $this->intUserArray[strtolower($objUser->Username)] = $objUser->UserAccountId;
            }*/
            $this->strAcceptibleMimeArray = array(
                'text/plain' => 'txt',
                'text/comma-separated-values' => 'csv',
                'text/csv' => 'csv',
                'text/x-comma-separated-values' => 'csv',
                'application/vnd.ms-excel' => 'csv',
                'application/csv' => 'csv',
                'text/x-csv' => 'csv');
        }
    }

    protected function Form_PreRender()
    {

        if ($this->dtgAsset && count($this->objNewAssetArray) > 0 && $this->dtgAsset->Paginator) {
            $this->dtgAsset->TotalItemCount = count($this->objNewAssetArray);
            $this->dtgAsset->DataSource = $this->return_array_chunk($this->dtgAsset, $this->objNewAssetArray);
        }

        if ($this->dtgUpdatedAsset && count($this->objUpdatedAssetArray) > 0 && $this->dtgUpdatedAsset->Paginator) {
            $this->dtgUpdatedAsset->TotalItemCount = count($this->objUpdatedAssetArray);
            $this->dtgUpdatedAsset->DataSource = $this->return_array_chunk($this->dtgUpdatedAsset, $this->objUpdatedAssetArray);
        }

    }

    // Create labels
    protected function Labels_Create()
    {
        $this->lblImportResults = new QLabel($this);
        $this->lblImportResults->HtmlEntities = false;
        $this->lblImportResults->Display = false;
        $this->lblImportResults->CssClass = "title";
        $this->lblImportResults->Text = "Import Results<br/><br/>";

        $this->lblImportUpdatedAssets = new QLabel($this);
        $this->lblImportUpdatedAssets->HtmlEntities = false;
        $this->lblImportUpdatedAssets->Display = false;
        $this->lblImportUpdatedAssets->CssClass = "title";
        $this->lblImportUpdatedAssets->Text = "<br/>Last Updated Assets";

        $this->lblImportAsset = new QLabel($this);
        $this->lblImportAsset->HtmlEntities = false;
        $this->lblImportAsset->Display = false;
        $this->lblImportAsset->CssClass = "title";
        $this->lblImportAsset->Text = "<br/>Last Imported Assets";
    }

    // Create and Setup the Header Composite Control
    protected function ctlHeaderMenu_Create()
    {
        $this->ctlHeaderMenu = new QHeaderMenu($this);
    }

    // Main Panel
    protected function pnlMain_Create()
    {
        $this->pnlMain = new QPanel($this);
        $this->pnlMain->AutoRenderChildren = true;
    }

    // Step 1 Panel
    protected function pnlStepOne_Create()
    {
        $this->pnlStepOne = new QPanel($this->pnlMain);
        $this->pnlStepOne->Template = "asset_import_pnl_step1.tpl.php";

        // Step 1
        $this->lstFieldSeparator = new QRadioButtonList($this->pnlStepOne);
        $this->lstFieldSeparator->Name = "Field Separator: ";
        $this->lstFieldSeparator->Width = 150;
        $this->lstFieldSeparator->AddItem(new QListItem('Comma Separated', 1));
        $this->lstFieldSeparator->AddItem(new QListItem('Tab Separated', 2));
        $this->lstFieldSeparator->AddItem(new QListItem('Other', 'other'));
        $this->lstFieldSeparator->SelectedIndex = 0;
        $this->lstFieldSeparator->AddAction(new QChangeEvent(), new QAjaxAction('lstFieldSeparator_Change'));
        $this->txtFieldSeparator = new QTextBox($this->pnlStepOne);
        $this->txtFieldSeparator->Width = 50;
        $this->txtFieldSeparator->SetCustomStyle('margin-left', '26px');
        $this->txtFieldSeparator->MaxLength = 1;
        $this->txtFieldSeparator->Display = false;
        $this->lstTextDelimiter = new QListBox($this->pnlStepOne);
        $this->lstTextDelimiter->Name = "Text Delimiter: ";
        $this->lstTextDelimiter->Width = 170;
        $this->lstTextDelimiter->AddItem(new QListItem('None', 1));
        $this->lstTextDelimiter->AddItem(new QListItem('Single Quote (\')', 2));
        $this->lstTextDelimiter->AddItem(new QListItem('Double Quote (")', 3));
        $this->lstTextDelimiter->AddItem(new QListItem('Other', 'other'));
        $this->lstTextDelimiter->AddAction(new QChangeEvent(), new QAjaxAction('lstTextDelimiter_Change'));
        $this->txtTextDelimiter = new QTextBox($this->pnlStepOne);
        $this->txtTextDelimiter->Name = 'Other: ';
        $this->txtTextDelimiter->Width = 50;
        $this->txtTextDelimiter->MaxLength = 1;
        $this->txtTextDelimiter->Display = false;
        $this->flcFileCsv = new QFileControlExt($this->pnlStepOne);
        $this->flcFileCsv->Name = "Select File: ";
        $this->chkHeaderRow = new QCheckBox($this->pnlStepOne);
        $this->chkHeaderRow->Name = "Header Row: ";
        $this->lstImportAction = new QRadioButtonList($this->pnlStepOne);
        $this->lstImportAction->Name = "Action Import: ";
        $this->lstImportAction->Width = 150;
        $this->lstImportAction->AddItem(new QListItem('Create Records', 1));
        $this->lstImportAction->AddItem(new QListItem('Create and Update Records', 2));
        $this->lstImportAction->SelectedIndex = 0;
    }


    // Step 2 Panel
    protected function pnlStepTwo_Create()
    {
        $this->pnlStepTwo = new QPanel($this->pnlMain);
        $this->pnlStepTwo->Template = "asset_import_pnl_step2.tpl.php";
    }

    // Step 3 Panel
    protected function pnlStepThree_Create()
    {
        $this->pnlStepThree = new QPanel($this->pnlMain);
        $this->pnlStepThree->Template = "asset_import_pnl_step3.tpl.php";
    }

    protected function Buttons_Create()
    {
        // Buttons
        $this->btnNext = new QButton($this);
        $this->btnNext->Text = "Next";
        $this->btnNext->AddAction(new QClickEvent(), new QServerAction('btnNext_Click'));
        $this->btnNext->AddAction(new QEnterKeyEvent(), new QServerAction('btnNext_Click'));
        $this->btnNext->AddAction(new QEnterKeyEvent(), new QTerminateAction());
        $this->btnCancel = new QButton($this);
        $this->btnCancel->Text = "Cancel";
        $this->btnCancel->AddAction(new QClickEvent(), new QServerAction('btnCancel_Click'));
        $this->btnCancel->AddAction(new QEnterKeyEvent(), new QServerAction('btnCancel_Click'));
        $this->btnCancel->AddAction(new QEnterKeyEvent(), new QTerminateAction());
    }

    protected function lstFieldSeparator_Change()
    {
        switch ($this->lstFieldSeparator->SelectedValue) {
            case 'other':
                $this->txtFieldSeparator->Display = true;
                break;
            default:
                $this->txtFieldSeparator->Display = false;
        }
    }

    protected function lstTextDelimiter_Change()
    {
        switch ($this->lstTextDelimiter->SelectedValue) {
            case 'other':
                $this->txtTextDelimiter->Display = true;
                break;
            default:
                $this->txtTextDelimiter->Display = false;
        }
    }

    // Next button click action
    protected function btnNext_Click()
    {
        $blnError = false;
        if ($this->intStep == 1) {
            if ($this->chkHeaderRow->Checked) {
                $this->blnHeaderRow = true;
            } else {
                $this->blnHeaderRow = false;
            }
            // Check errors
            if ($this->lstFieldSeparator->SelectedValue == 'other' && !$this->txtFieldSeparator->Text) {
                $this->flcFileCsv->Warning = "Please enter the field separator.";
                $blnError = true;
            } elseif ($this->lstTextDelimiter->SelectedValue == 'other' && !$this->txtTextDelimiter->Text) {
                $this->flcFileCsv->Warning = "Please enter the text delimiter.";
                $blnError = true;
            } else {
                // Step 1 complete
                // File Not Uploaded
                if (!file_exists($this->flcFileCsv->File) || !$this->flcFileCsv->Size) {
                    //throw new QCallerException('FileAssetType must be a valid QFileAssetType constant value');
                    $this->flcFileCsv->Warning = 'The file could not be uploaded. Please provide a valid file.';
                    $blnError = true;
                    // File Has Incorrect MIME Type (only if an acceptiblemimearray is setup)
                } elseif (is_array($this->strAcceptibleMimeArray) && (!array_key_exists($this->flcFileCsv->Type, $this->strAcceptibleMimeArray))) {
                    $this->flcFileCsv->Warning = "Extension must be 'csv' or 'txt'";
                    $blnError = true;
                    // File Successfully Uploaded
                } else {
                    $this->flcFileCsv->Warning = "";
                    // Setup Filename, Base Filename and Extension
                    $strFilename = $this->flcFileCsv->FileName;
                    $intPosition = strrpos($strFilename, '.');
                }
                if (!$blnError) {
                    $this->FileCsvData = new File_CSV_DataSource();
                    // Setup the settings which have got on step 1
                    $this->FileCsvData->settings($this->GetCsvSettings());
                    $file = fopen($this->flcFileCsv->File, "r");
                    // Counter of files
                    $i = 1;
                    // Counter of rows
                    $j = 1;
                    $this->strFilePathArray = array();
                    // The uploaded file splits up in order to avoid out of memory
                    while ($row = fgets($file, 1000)) {
                        if ($j == 1) {
                            $strFilePath = sprintf('%s/%s_%s.csv', __DOCROOT__ . __SUBDIRECTORY__ . __TRACMOR_TMP__, $_SESSION['intUserAccountId'], $i);
                            $this->strFilePathArray[] = $strFilePath;
                            $file_part = fopen($strFilePath, "w+");
                            if ($i == 1) {
                                $strHeaderRow = $row;
                            } else {
                                fwrite($file_part, $strHeaderRow);
                            }
                        }

                        fwrite($file_part, $row);
                        $j++;
                        if ($j > 200) {
                            $j = 1;
                            $i++;
                            fclose($file_part);
                        }
                    }
                    $this->intTotalCount = ($i - 1) * 200 + $j - 1;
                    if (QApplication::$TracmorSettings->AssetLimit != null && QApplication::$TracmorSettings->AssetLimit < ($this->intTotalCount + Asset::CountAll())) {
                        $blnError = true;
                        $this->btnNext->Warning = $i . " " . $j . "Sorry that is too many assets. Your asset limit is = " . QApplication::$TracmorSettings->AssetLimit . ", this import has " . ($this->intTotalCount) . " assets, and you already have " . Asset::CountAll() . " assets in the database.";
                    } else {
                        $this->arrMapFields = array();
                        $this->arrTracmorField = array();
                        // Load first file
                        $this->FileCsvData->load($this->strFilePathArray[0]);
                        $file_skipped = fopen($this->strFilePath = sprintf('%s/%s_skipped.csv', __DOCROOT__ . __SUBDIRECTORY__ . __TRACMOR_TMP__, $_SESSION['intUserAccountId']), "w+");
                        // Get Headers
                        if ($this->blnHeaderRow) {
                            $this->arrCsvHeader = $this->FileCsvData->getHeaders();
                            // Create the header row in the skipped error file
                            $this->PutSkippedRecordInFile($file_skipped, $this->arrCsvHeader);
                        }
                        /*else {
                          // If it is not first file
                          $this->FileCsvData->appendRow($this->FileCsvData->getHeaders());
                        }*/
                        $strFirstRowArray = $this->FileCsvData->getRow(0);
                        for ($i = 0; $i < count($strFirstRowArray); $i++) {
                            $this->arrMapFields[$i] = array();
                            if ($this->blnHeaderRow && array_key_exists($i, $this->arrCsvHeader)) {
                                if ($this->arrCsvHeader[$i] == '') {
                                    $this->arrCsvHeader[$i] = ' ';
                                }
                                $this->lstMapHeader_Create($this, $i, $this->arrCsvHeader[$i]);
                                $this->arrMapFields[$i]['header'] = $this->arrCsvHeader[$i];
                            } else {
                                $this->lstMapHeader_Create($this, $i);
                            }
                            // Create Default Value TextBox, ListBox and DateTimePicker
                            if ($this->blnHeaderRow && array_key_exists($i, $this->arrCsvHeader) && $this->arrCsvHeader[$i] || !$this->blnHeaderRow) {
                                $txtDefaultValue = new QTextBox($this);
                                $txtDefaultValue->Width = 200;
                                $this->txtMapDefaultValueArray[] = $txtDefaultValue;

                                $lstDefaultValue = new QListBox($this);
                                $lstDefaultValue->Width = 200;
                                $lstDefaultValue->Display = false;
                                $this->lstMapDefaultValueArray[] = $lstDefaultValue;

                                $dtpDate = new QDateTimePicker($this);
                                $dtpDate->DateTimePickerType = QDateTimePickerType::Date;
                                $dtpDate->DateTimePickerFormat = QDateTimePickerFormat::MonthDayYear;
                                $dtpDate->Display = false;
                                $this->dtpDateArray[] = $dtpDate;

                                if (array_key_exists($i, $this->lstMapHeaderArray)) {
                                    $this->lstTramorField_Change(null, $this->lstMapHeaderArray[$i]->ControlId, null);
                                }
                            }
                            $this->arrMapFields[$i]['row1'] = $strFirstRowArray[$i];
                        }
                        $this->btnNext->Text = "Import Now";
                        fclose($file_skipped);
                        // Create Add Field button
                        $btnAddField = new QButton($this);
                        $btnAddField->Text = "Add Field";
                        $btnAddField->AddAction(new QClickEvent(), new QServerAction('btnAddField_Click'));
                        $btnAddField->AddAction(new QEnterKeyEvent(), new QServerAction('btnAddField_Click'));
                        $btnAddField->AddAction(new QEnterKeyEvent(), new QTerminateAction());
                        $this->lstMapHeaderArray[] = $btnAddField;
                    }
                }
            }
        } elseif ($this->intStep == 2) {
            // Step 2 complete
            $blnRequiredAllAssetCustomFields = true;
            $blnError = false;
            $blnAssetCode = false;
            $blnAssetModelShortDescription = false;
            $blnLocation = false;
            $blnAssetId = false;

            // $array Required Custom Fields for All Asset Models
            $arrAssetCustomFieldOptions = EntityQtypeCustomField::LoadArrayByEntityQtypeId(
                QApplication::Translate(EntityQtype::Asset));
            // generate error flag and field names which are required for export;
            $arrRequiredAllAssetCustomFields = array();
            foreach ($arrAssetCustomFieldOptions as $arrAssetCustomFieldOption) {
                if ($arrAssetCustomFieldOption->CustomField->RequiredFlag
                    && $arrAssetCustomFieldOption->CustomField->ActiveFlag
                    && $arrAssetCustomFieldOption->CustomField->AllAssetModelsFlag
                ) {
                    $arrRequiredAllAssetCustomFields[] = $arrAssetCustomFieldOption->CustomField->ShortDescription;
                    if (!in_array($arrAssetCustomFieldOption->CustomField->ShortDescription, $this->getLstKeys())) {
                        $blnRequiredAllAssetCustomFields = false;
                    }
                }
            }
            // Checking errors (Model Short Description, Model Code, Category and Manufacturer must be selected)
            for ($i = 0; $i < count($this->lstMapHeaderArray) - 1; $i++) {
                $lstMapHeader = $this->lstMapHeaderArray[$i];
                $strSelectedValue = strtolower($lstMapHeader->SelectedValue);
                if ($strSelectedValue == "model") {
                    $blnAssetModelShortDescription = true;
                } elseif ($strSelectedValue == "asset tag") {
                    $blnAssetCode = true;
                } elseif ($strSelectedValue == "location") {
                    $blnLocation = true;
                } elseif ($strSelectedValue == "id") {
                    $blnAssetId = true;
                }
            }
            if ($this->lstMapDefaultValueArray) {
                // Checking errors for required Default Value text fields
                foreach ($this->lstMapDefaultValueArray as $lstDefault) {
                    if ($lstDefault->Display && $lstDefault->Required && !$lstDefault->SelectedValue) {
                        $lstDefault->Warning = "You must select one default value.";
                        $blnError = true;
                        break;
                    } else {
                        $blnError = false;
                        $lstDefault->Warning = "";
                    }
                }
            }
            if ($this->txtMapDefaultValueArray) {
                // Checking errors for required Default Value lst fields
                foreach ($this->txtMapDefaultValueArray as $txtDefault) {
                    if ($txtDefault->Display && $txtDefault->Required && !$txtDefault->Text) {
                        $txtDefault->Warning = "You must enter default value.";
                        break;
                    } else {
                        $blnError = false;
                        $txtDefault->Warning = "";
                    }
                }
            }
            // If all required fields have no errors
            if (!$blnError
                && $blnRequiredAllAssetCustomFields
                && $blnAssetCode
                && $blnAssetModelShortDescription
                && $blnLocation
                && ($this->lstImportAction->SelectedValue != 2 || $blnAssetId)
            ) {
                $this->btnNext->Warning = "";
                // Setup keys for main required fields
                foreach ($this->arrTracmorField as $key => $value) {
                    /*if ($value == 'category') {
                      $this->intCategoryKey = $key;
                    }
                    elseif ($value == 'manufacturer') {
                      $this->intManufacturerKey = $key;
                    }
                    else*/
                    if ($this->lstImportAction->SelectedValue == 2 && $value == 'id') {
                        $this->intItemIdKey = $key;
                    }
                    /*elseif ($value == 'created by') {
                      $this->intCreatedByKey = $key;
                    }
                    elseif ($value == 'created date') {
                      $this->intCreatedDateKey = $key;
                    }
                    elseif ($value == 'modified by') {
                      $this->intModifiedByKey = $key;
                    }
                    elseif ($value == 'modified date') {
                      $this->intModifiedDateKey = $key;
                    }*/
                }

                $this->objNewAssetArray = array();
                $this->strAssetValuesArray = array();
                $this->blnImportEnd = false;
                $j = 1;

                $this->btnNext->RemoveAllActions('onclick');
                // Add new ajax actions for button
                $this->btnNext->AddAction(new QClickEvent(), new QAjaxAction('btnNext_Click'));
                $this->btnNext->AddAction(new QClickEvent(), new QToggleEnableAction($this->btnNext));
                $this->btnNext->AddAction(new QEnterKeyEvent(), new QAjaxAction('btnNext_Click'));
                $this->btnNext->AddAction(new QEnterKeyEvent(), new QToggleEnableAction($this->btnNext));
                $this->btnNext->AddAction(new QEnterKeyEvent(), new QTerminateAction());
                $this->btnNext->Warning = "Please wait...";
                $this->intImportStep = 2;
                $this->intCurrentFile = 0;
                $this->strSelectedValueArray = array();

                // New asset models
                $this->dtgAsset = new QDataGrid($this);
                $this->dtgAsset->Name = 'asset_list';
                $this->dtgAsset->CellPadding = 5;
                $this->dtgAsset->CellSpacing = 0;
                $this->dtgAsset->CssClass = "datagrid";
                $this->dtgAsset->UseAjax = true;
                $this->dtgAsset->ShowColumnToggle = false;
                $this->dtgAsset->ShowExportCsv = false;
                $this->dtgAsset->ShowHeader = false;
                $this->dtgAsset->AddColumn(new QDataGridColumnExt('Model', '<?= $_ITEM ?>', 'CssClass="dtg_column"', 'HtmlEntities="false"'));

                // Updated assets
                $this->dtgUpdatedAsset = new QDataGrid($this);
                $this->dtgUpdatedAsset->Name = 'updated_asset_list';
                $this->dtgUpdatedAsset->CellPadding = 5;
                $this->dtgUpdatedAsset->CellSpacing = 0;
                $this->dtgUpdatedAsset->CssClass = "datagrid";
                $this->dtgUpdatedAsset->UseAjax = true;
                $this->dtgUpdatedAsset->ShowColumnToggle = false;
                $this->dtgUpdatedAsset->ShowExportCsv = false;
                $this->dtgUpdatedAsset->ShowHeader = false;
                $this->dtgUpdatedAsset->AddColumn(new QDataGridColumnExt('Asset Tag', '<?= $_ITEM ?>', 'CssClass="dtg_column"', 'HtmlEntities="false"'));

                // Create the label for successful import
                $this->lblImportSuccess = new QLabel($this);
                $this->lblImportSuccess->HtmlEntities = false;
                $this->lblImportSuccess->Display = false;

                // Undo Last Import button
                $this->btnUndoLastImport = new QButton($this);
                $this->btnUndoLastImport->Text = "Undo Last Import";
                $this->btnUndoLastImport->Display = false;
                $this->btnUndoLastImport->AddAction(new QClickEvent(), new QServerAction('btnCancel_Click'));
                $this->btnUndoLastImport->AddAction(new QEnterKeyEvent(), new QServerAction('btnCancel_Click'));
                $this->btnUndoLastImport->AddAction(new QEnterKeyEvent(), new QTerminateAction());

                // Import More button
                $this->btnImportMore = new QButton($this);
                $this->btnImportMore->Text = "Import More";
                $this->btnImportMore->Display = false;
                $this->btnImportMore->AddAction(new QClickEvent(), new QServerAction('btnImportMore_Click'));
                $this->btnImportMore->AddAction(new QEnterKeyEvent(), new QServerAction('btnImportMore_Click'));
                $this->btnImportMore->AddAction(new QEnterKeyEvent(), new QTerminateAction());

                // Return to Assets button
                $this->btnReturnToAssets = new QButton($this);
                $this->btnReturnToAssets->Text = "Return to Assets";
                $this->btnReturnToAssets->Display = false;
                $this->btnReturnToAssets->AddAction(new QClickEvent(), new QServerAction('btnReturnToAssets_Click'));
                $this->btnReturnToAssets->AddAction(new QEnterKeyEvent(), new QServerAction('btnReturnToAssets_Click'));
                $this->btnReturnToAssets->AddAction(new QEnterKeyEvent(), new QTerminateAction());
            } else {
                $strRequiredAllAssetCustomFields = '';
                if (count($arrRequiredAllAssetCustomFields) > 0) {
                    $strRequiredAllAssetCustomFields = implode(", ", $arrRequiredAllAssetCustomFields) . ", ";
                }
                $this->btnNext->Warning = "You must select all required fields (" . $strRequiredAllAssetCustomFields . "Asset Tag, Model Short Description and Location).";
                $blnError = true;
            }
        } else {
            // Step 3 complete
            set_time_limit(0);
            $file_skipped = fopen($strFilePath = sprintf('%s/%s_skipped.csv', __DOCROOT__ . __SUBDIRECTORY__ . __TRACMOR_TMP__, $_SESSION['intUserAccountId']), "a");
            if (!$this->blnImportEnd) {
                // Asset
                if ($this->intImportStep == 2) {
                    $intCategoryArray = array();
                    // Load all categories with key=category_id
                    /*foreach (Category::LoadAllWithFlags(true, false) as $objCategory) {
                      $intCategoryArray[$objCategory->CategoryId] = strtolower($objCategory->ShortDescription);
                    }
                    $intManufacturerArray = array();
                    // Load all manufacturers with key=manufacturer_id
                    foreach (Manufacturer::LoadAll() as $objManufacturer) {
                      $intManufacturerArray[$objManufacturer->ManufacturerId] = strtolower($objManufacturer->ShortDescription);
                    }*/

                    $intAssetCustomFieldKeyArray = array();
                    $arrAssetCustomField = array();
                    // Setup keys
                    foreach ($this->arrTracmorField as $key => $value) {
                        if ($value == 'asset tag') {
                            $intAssetCodeKey = $key;
                        } elseif ($value == 'model') {
                            $intAssetModelDescriptionKey = $key;
                        } elseif ($value == 'location') {
                            $intLocationKey = $key;
                        } elseif ($value == 'parent asset') {
                            $intParentAssetKey = $key;
                        } elseif ($value == 'locked to parent') {
                            $intLinkedKey = $key;
                        } elseif (substr($value, 0, 6) == 'asset_') {
                            $intAssetCustomFieldKeyArray[substr($value, 6)] = $key;
                            if (array_key_exists(substr($value, 6), $this->arrAssetCustomField)) {
                                $arrAssetCustomField[substr($value, 6)] = $this->arrAssetCustomField[substr($value, 6)];
                            }
                        }
                    }
                    $intAssetModelArray = array();
                    $strItemCFVArray = array();
                    $strUpdatedItemCFVArray = array();
                    $strUpdatedValuesArray = array();
                    $this->arrOldItemArray = array();
                    $this->objUpdatedItemArray = array();

                    // Load all asset models
                    foreach (AssetModel::LoadAllIntoArray() as $arrAssetModel) {
                        //$strAssetModelArray[] = strtolower(sprintf("%s_%s_%s_%s", addslashes($arrAssetModel['model_code']),  addslashes($arrAssetModel['short_description']),  $arrAssetModel['category_id'], $arrAssetModel['manufacturer_id']));
                        $intAssetModelArray[$arrAssetModel['asset_model_id']] = strtolower($arrAssetModel['short_description']);
                    }
                    $intLocationArray = array();
                    // Load all locations with keys=location_id
                    foreach (Location::LoadAll() as $objLocation) {
                        $intLocationArray[$objLocation->LocationId] = strtolower($objLocation->ShortDescription);
                    }

                    $strAssetArray = array();
                    // Load all assets
                    // Loads array of AssetModelId
                    $arrAssetArray = Asset::LoadAllIntoArray();
                    $arrAssetId = array();
                    if (count($arrAssetArray))
                        foreach ($arrAssetArray as $arrAsset) {
                            $arrAssetId[$arrAsset['asset_id']] = addslashes(strtolower($arrAsset['asset_code']));
                            $strAssetArray[] = addslashes(strtolower($arrAsset['asset_code']));
                        }
                }

                for ($j = $this->intCurrentFile; $j < count($this->strFilePathArray); $j++) {
                    $this->FileCsvData->load($this->strFilePathArray[$j]);
                    if (!$j) {
                        //$this->FileCsvData->appendRow($this->FileCsvData->getHeaders());
                    }
                    if ($this->intImportStep == 2) {
                        $strAssetCFVArray = array();
                        $objNewAssetArray = array();
                        for ($i = 0; $i < $this->FileCsvData->countRows(); $i++) {
                            $strRowArray = $this->FileCsvData->getRow($i);
                            $strAssetCode = (trim($strRowArray[$intAssetCodeKey])) ? addslashes(trim($strRowArray[$intAssetCodeKey])) : false;
                            //$strAssetCode = (trim($strRowArray[$intAssetCodeKey])) ? trim($strRowArray[$intAssetCodeKey]) : false;
                            $strKeyArray = array_keys($intLocationArray, (isset($strRowArray[$intLocationKey])) ? strtolower(trim($strRowArray[$intLocationKey])) : array());
                            if (count($strKeyArray)) {
                                $intLocationId = $strKeyArray[0];
                            } else {
                                $strKeyArray = array_keys($intLocationArray, strtolower(trim($this->txtMapDefaultValueArray[$intLocationKey]->Text)));
                                if (count($strKeyArray)) {
                                    $intLocationId = $strKeyArray[0];
                                } else {
                                    $intLocationId = false;
                                }
                            }
                            $strKeyArray = array_keys($intAssetModelArray, strtolower(trim($strRowArray[$intAssetModelDescriptionKey])));
                            if (count($strKeyArray)) {
                                $intAssetModelId = $strKeyArray[0];
                            } else {
                                $strKeyArray = array_keys($intAssetModelArray, strtolower(trim($this->txtMapDefaultValueArray[$intAssetModelDescriptionKey]->Text)));
                                if (count($strKeyArray)) {
                                    $intAssetModelId = $strKeyArray[0];
                                } else {
                                    $intAssetModelId = false;
                                }
                            }
                            $blnError = false;
                            // Skip records with not filled required custom fields
                            $log = '';
                            if ($intAssetModelId) {
                                foreach ($intAssetCustomFieldKeyArray as $k => $v) {
                                    $log .= 'k' . $k . '=' . $v;
                                }

                                // Load Specific asset model custom field
                                $arrRequiredSpecificAssetCustomFields = AssetCustomFieldAssetModel::
                                    LoadArrayByAssetModelId($intAssetModelId);

                                foreach ($arrRequiredSpecificAssetCustomFields as $objRequiredCustomField) {
                                    if ($objRequiredCustomField->CustomField->RequiredFlag) {
                                        $log .= $objRequiredCustomField->CustomField->CustomFieldId;
                                        if (!array_key_exists($objRequiredCustomField->CustomField->CustomFieldId, $intAssetCustomFieldKeyArray)
                                            || empty($strRowArray[array_search($objRequiredCustomField->CustomField->CustomFieldId,
                                                $intAssetCustomFieldKeyArray)])
                                        ) {
                                            $blnError = true;
                                            $this->intImportStep = 3;
                                            //   $log .= "<h1>".$objRequiredCustomField->CustomField->ShortDescription.$objRequiredCustomField->CustomField->CustomFieldId." is empty</h1>";
                                        }
                                    }
                                }
                            }
                            //print $log; exit;
                            if (!$blnError) {
                                if (isset($intParentAssetKey) && isset($strRowArray[$intParentAssetKey])) {
                                    $strKeyArray = array_keys($arrAssetId, strtolower(trim($strRowArray[$intParentAssetKey])));
                                    if (count($strKeyArray)) {
                                        $intParentAssetId = $strKeyArray[0];
                                    } else {
                                        $strKeyArray = array_keys($arrAssetId, strtolower(trim($this->txtMapDefaultValueArray[$intParentAssetKey]->Text)));
                                        if (count($strKeyArray)) {
                                            $intParentAssetId = $strKeyArray[0];
                                        } else {
                                            $intParentAssetId = null;
                                            $blnError = true;
                                        }
                                    }
                                    if (!$intParentAssetId || $strRowArray[$intParentAssetKey] == $strAssetCode) {
                                        $blnError = true;
                                    }
                                    $blnLinked = ($intParentAssetId && isset($intLinkedKey) && strlen(trim($strRowArray[$intLinkedKey])) > 0) ? 1 : 0;
                                } else {
                                    $intParentAssetId = null;
                                    $blnLinked = 0;
                                }
                            }
                            /*$strKeyArray = array_keys($intCategoryArray, strtolower(trim($strRowArray[$this->intCategoryKey])));
                            if (count($strKeyArray)) {
                              $intCategoryId = $strKeyArray[0];
                            }
                            else {
                              $strKeyArray = array_keys($intCategoryArray, strtolower(trim($this->txtMapDefaultValueArray[$this->intCategoryKey]->Text)));
                              if (count($strKeyArray)) {
                                $intCategoryId = $strKeyArray[0];
                              }
                              else {
                                $intCategoryId = false;
                              }
                            }
                            $strKeyArray = array_keys($intManufacturerArray, strtolower(trim($strRowArray[$this->intManufacturerKey])));
                            if (count($strKeyArray)) {
                              $intManufacturerId = $strKeyArray[0];
                            }
                            else {
                              $strKeyArray = array_keys($intManufacturerArray, strtolower(trim($this->txtMapDefaultValueArray[$this->intManufacturerKey]->Text)));
                              if (count($strKeyArray)) {
                                $intManufacturerId = $strKeyArray[0];
                              }
                              else {
                                $intManufacturerId = false;
                              }
                            }*/
                            $objAsset = false;
                            if (!$strAssetCode || $blnError || $intAssetModelId === false || $intLocationId === false /* || $intCategoryId === false || $intManufacturerId === false*/) {
                                //$blnError = true;
                                //echo sprintf("Desc: %s AssetCode: %s Cat: %s Man: %s<br/>", $strAssetCode, $strLocation, $intCategoryId, $intManufacturerId);
                                $strAssetCode = null;
                                $this->intSkippedRecordCount++;
                                $this->PutSkippedRecordInFile($file_skipped, $strRowArray);
                            } else {
                                if ($this->lstImportAction->SelectedValue == 2) {
                                    $intItemId = intval(trim($strRowArray[$this->intItemIdKey]));
                                    if ($intItemId > 0 && array_key_exists($intItemId, $arrAssetId)) {
                                        //QApplication::$Database[1]->EnableProfiling();
                                        $objAssetArray = Asset::LoadArrayBySearchHelper(null, null, null, null, null, false, null, null, null, null, null, null, null, null, null, false, null, null, null, false, false, false, null, null, false, $intItemId);
                                        //QApplication::$Database[1]->OutputProfiling();
                                        if ($objAssetArray)
                                            $objAsset = $objAssetArray[0];
                                    }
                                } else {
                                    $intItemId = 0;
                                }
                            }

                            if ($strAssetCode && !$intItemId && !$this->in_array_nocase($strAssetCode, $strAssetArray)) {
                                // Custom Fields Section
                                $strCFVArray = array();
                                $objDatabase = CustomField::GetDatabase();
                                $blnCheckCFVError = false;
                                // Asset Model Custom Field import
                                foreach ($arrAssetCustomField as $objCustomField) {
                                    if ($objCustomField->CustomFieldQtypeId != 2) {
                                        $strCSDescription = (isset($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]])) ?
                                            trim($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]) :
                                            "";
                                        $strCSDescription = (strlen($strCSDescription) > 0) ?
                                            addslashes($strCSDescription) :
                                            addslashes($this->txtMapDefaultValueArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]->Text);
                                        $strCFVArray[$objCustomField->CustomFieldId] = (strlen($strCSDescription) > 0) ? sprintf("'%s'", $strCSDescription) : "NULL";
                                    } else {
                                        $objDatabase = Asset::GetDatabase();
                                        $strCSDescription = (isset($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]])) ?
                                            addslashes(trim($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]])) :
                                            "";
                                        $blnInList = false;
                                        foreach (CustomFieldValue::LoadArrayByCustomFieldId($objCustomField->CustomFieldId) as $objCustomFieldValue) {
                                            if (strtolower($objCustomFieldValue->ShortDescription) == strtolower($strCSDescription)) {
                                                //$intCustomFieldValueId = $objCustomFieldValue->CustomFieldValueId;
                                                $blnInList = true;
                                                break;
                                            }
                                        }
                                        // Add the CustomFieldValue
                                        // Removed adding new 'select' values
                                        /*if (!$blnInList && !in_array($strCSDescription, $strAddedCFVArray)) {
                                            $strQuery = sprintf("INSERT INTO custom_field_value (custom_field_id, short_description, created_by, creation_date) VALUES (%s, '%s', %s, NOW());", $objCustomField->CustomFieldId, $strCSDescription, $_SESSION['intUserAccountId']);
                                            $objDatabase->NonQuery($strQuery);
                                            $strAddedCFVArray[] = $strCSDescription;
                                        }
                                        else*/
                                        if (!$blnInList && $this->lstMapDefaultValueArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]->SelectedValue != null) {
                                            $strCSDescription = $this->lstMapDefaultValueArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]->SelectedName;
                                        } elseif (!$blnInList) {
                                            $blnCheckCFVError = true;
                                            break;
                                        }
                                        if (!$blnCheckCFVError)
                                            if ($strCSDescription /* && $intCustomFieldValueId*/) {
                                                $strCFVArray[$objCustomField->CustomFieldId] = sprintf("'%s'", $strCSDescription);
                                            } else {
                                                $strCFVArray[$objCustomField->CustomFieldId] = "NULL";
                                            }
                                    }
                                }

                                if (!$blnCheckCFVError) {
                                    $strAssetArray[] = stripslashes($strAssetCode);
                                    $this->strAssetValuesArray[] = sprintf("('%s', '%s', '%s', %s, %s, '%s', NOW())", $strAssetCode, $intLocationId, $intAssetModelId, ($intParentAssetId) ? $intParentAssetId : "NULL", ($blnLinked) ? "1" : "0", $_SESSION['intUserAccountId']);
                                    $objNewAssetArray[] = stripslashes($strAssetCode);
                                    if (isset($strCFVArray) && count($strCFVArray)) {
                                        $strAssetCFVArray[] = str_replace('""', '"', implode(', ', $strCFVArray));
                                        /*if ($j == 2) {
                                             echo "strAssetCFVArray: ";
                                             print_r($strAssetCFVArray);
                                             echo "strCFVArray: ";
                                             print_r($strCFVArray);
                                             exit;
                                         }*/
                                    } else {
                                        $strAssetCFVArray[] = "";
                                    }
                                } else {
                                    $this->intSkippedRecordCount++;
                                    $this->PutSkippedRecordInFile($file_skipped, $strRowArray);
                                    $strAssetCode = null;
                                }
                            } // Import Action is "Create and Update Records"
                            elseif ($strAssetCode && $this->lstImportAction->SelectedValue == 2 && $objAsset) {
                                $strUpdateFieldArray = array();
                                $strUpdateFieldArray[] = sprintf("`asset_code`='%s'", $strAssetCode);
                                $strUpdateFieldArray[] = sprintf("`asset_model_id`='%s'", $intAssetModelId);
                                $strUpdateFieldArray[] = sprintf("`parent_asset_id`=%s", QApplication::$Database[1]->SqlVariable($intParentAssetId));
                                $strUpdateFieldArray[] = sprintf("`linked_flag`='%s'", ($blnLinked) ? 1 : 0);
                                $strUpdateFieldArray[] = sprintf("`modified_by`='%s'", $_SESSION['intUserAccountId']);

                                $blnCheckCFVError = false;
                                foreach ($arrAssetCustomField as $objCustomField) {
                                    if ($objCustomField->CustomFieldQtypeId != 2) {
                                        $strCSDescription = (isset($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]])) ?
                                            trim($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]) :
                                            "";
                                        $strCSDescription = (strlen($strCSDescription) > 0) ?
                                            addslashes($strCSDescription) :
                                            addslashes($this->txtMapDefaultValueArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]->Text);
                                        $strCFVArray[$objCustomField->CustomFieldId] = (strlen($strCSDescription) > 0) ? sprintf("'%s'", $strCSDescription) : "NULL";
                                    } else {
                                        $objDatabase = CustomField::GetDatabase();
                                        $strCSDescription = (isset($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]])) ?
                                            addslashes(trim($strRowArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]])) :
                                            "";
                                        $strCFVArray[$objCustomField->CustomFieldId] = ($strCSDescription) ? sprintf("'%s'", $strCSDescription) : "NULL";
                                        $blnInList = false;
                                        foreach (CustomFieldValue::LoadArrayByCustomFieldId($objCustomField->CustomFieldId) as $objCustomFieldValue) {
                                            if (strtolower($objCustomFieldValue->ShortDescription) == strtolower($strCSDescription)) {
                                                //$intItemKeyntCustomFieldValueId = $objCustomFieldValue->CustomFieldValueId;
                                                $blnInList = true;
                                                break;
                                            }
                                        }
                                        if (!$blnInList && $this->lstMapDefaultValueArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]->SelectedValue != null) {
                                            $strCSDescription = $this->lstMapDefaultValueArray[$intAssetCustomFieldKeyArray[$objCustomField->CustomFieldId]]->SelectedName;
                                        } elseif (!$blnInList) {
                                            $blnCheckCFVError = true;
                                            break;
                                        }
                                        if (!$blnCheckCFVError)
                                            if ($strCSDescription /* && $intCustomFieldValueId*/) {
                                                $strCFVArray[$objCustomField->CustomFieldId] = sprintf("'%s'", $strCSDescription);
                                            } else {
                                                $strCFVArray[$objCustomField->CustomFieldId] = "NULL";
                                            }
                                    }
                                }
                                // The user can not change location
                                if ($intLocationId != $objAsset->LocationId) {
                                    $blnCheckCFVError = true;
                                }
                                if (!$blnCheckCFVError) {
                                    $strUpdatedValuesArray[] = sprintf("UPDATE `asset` SET %s WHERE `asset_id`='%s'", str_replace('""', '"', implode(", ", $strUpdateFieldArray)), $objAsset->AssetId);
                                    if (isset($strCFVArray) && count($strCFVArray)) {
                                        $strUpdatedItemCFVArray[$objAsset->AssetId] = $strCFVArray;
                                    } else {
                                        $strUpdatedItemCFVArray[$objAsset->AssetId] = "";
                                    }
                                    $this->objUpdatedItemArray[$objAsset->AssetId] = sprintf("%s", $objAsset->AssetCode);
                                    //$this->arrOldItemArray[$objAsset->AssetId] = $objAsset;
                                    $strItemQuery = sprintf("UPDATE `asset` SET `asset_code`='%s', `asset_model_id`='%s', `parent_asset_id`=%s, `linked_flag`='%s', `modified_by`=%s, `modified_date`=%s WHERE `asset_id`='%s'", $objAsset->AssetCode, $objAsset->AssetModelId, (!$objAsset->ParentAssetId) ? "NULL" : $objAsset->ParentAssetId, $objAsset->LinkedFlag, (!$objAsset->ModifiedBy) ? "NULL" : $objAsset->ModifiedBy, (!$objAsset->ModifiedBy) ? "NULL" : sprintf("'%s'", $objAsset->ModifiedDate), $objAsset->AssetId);
                                    $strCFVArray = array();
                                    foreach ($this->arrAssetCustomField as $objCustomField) {
                                        $strCFV = $objAsset->GetVirtualAttribute($objCustomField->CustomFieldId);
                                        $strCFVArray[] = sprintf("`cfv_%s`='%s'", $objCustomField->CustomFieldId, $strCFV);
                                    }
                                    // Update values for custom field set not allowed to model
                                    $arrToClear = $this->substactNotAllowedFields($objAsset->AssetModelId, null);
                                    foreach ($arrToClear as $idToBeNull) {
                                        $strCFVArray[] = sprintf("`cfv_%s`= NULL", $idToBeNull);
                                    }

                                    if (isset($strCFVArray) && count($strCFVArray)) {
                                        $strCFVQuery = sprintf("UPDATE `asset_custom_field_helper` SET %s WHERE `asset_id`='%s'", str_replace('""', '"', implode(", ", $strCFVArray)), $intItemId);
                                    } else {
                                        $strCFVQuery = false;
                                    }
                                    $this->arrOldItemArray[$objAsset->AssetId] = array("ItemSql" => $strItemQuery, "CFVSql" => $strCFVQuery);
                                }
                            } // If Import Action is "Create Records" and this Asset has already in the database
                            /*elseif ($this->lstImportAction->SelectedValue == 1 && $this->in_array_nocase($strAssetCode, $strAssetArray)) {
                              // Skipped and flagged as duplicates
                              $this->intSkippedRecordCount++;
                              $this->PutSkippedRecordInFile($file_skipped, $strRowArray);
                            }*/
                            else {
                                $this->intSkippedRecordCount++;
                                $this->PutSkippedRecordInFile($file_skipped, $strRowArray);
                            }

                        }
                        //if ($this->intCurrentFile == count($this->strFilePathArray)) {
                        // Inserts
                        if (count($this->strAssetValuesArray)) {
                            $objDatabase = Asset::GetDatabase();

                            //var_dump($this->strAssetValuesArray);
                            //exit();

                            $objDatabase->NonQuery(sprintf("INSERT INTO `asset` (`asset_code`, `location_id`, `asset_model_id`, `parent_asset_id`, `linked_flag`, `created_by`, `creation_date`) VALUES %s;", str_replace('""', '"', implode(", ", $this->strAssetValuesArray))));
                            $intInsertId = $objDatabase->InsertId();
                            if ($intInsertId) {
                                $strAssetIdArray = array();
                                $strCFVArray = array();
                                for ($i = 0; $i < count($objNewAssetArray); $i++) {
                                    $this->objNewAssetArray[$intInsertId + $i] = $objNewAssetArray[$i];
                                    $strCFVArray[$i] = sprintf("('%s', %s)", $intInsertId + $i, $strAssetCFVArray[$i]);
                                    $strAssetIdArray[$i] = sprintf("(%s)", $intInsertId + $i);
                                }
                                /*if ($j == 1) {
                                    echo "strCFVArray: ";
                                    print_r($strCFVArray);
                                    echo "strAssetCFVArray: ";
                                    print_r($strAssetCFVArray);
                                    exit;
                                }*/
                                $strCFVNameArray = array();
                                foreach ($arrAssetCustomField as $objCustomField) {
                                    $strCFVNameArray[] = sprintf("`cfv_%s`", $objCustomField->CustomFieldId);
                                }
                                if (count($strAssetCFVArray) > 0 && count($strCFVNameArray) > 0) {
                                    $strQuery = sprintf("INSERT INTO `asset_custom_field_helper` (`asset_id`, %s) VALUES %s", str_replace('""', '"', implode(", ", $strCFVNameArray)), str_replace('""', '"', implode(", ", $strCFVArray)));
                                } else {
                                    $strQuery = sprintf("INSERT INTO `asset_custom_field_helper` (`asset_id`) VALUES %s", str_replace('""', '"', implode(", ", $strAssetIdArray)));
                                }
                                $objDatabase->NonQuery($strQuery);
                                $theAsset = Asset::Load($intInsertId);
                                $strQuery = $this->substactNotAllowedFields($theAsset->AssetModelId, $theAsset->AssetId);
                                if (!empty($strQuery)) {
                                    //$objDatabase->NonQuery($this->substactNotAllowedFields($theAsset->AssetModelId,$theAsset->AssetId));
                                    $objDatabase->NonQuery($strQuery);
                                }
                            }

                            $this->strAssetValuesArray = array();
                        }
                        // Updates
                        if (count($strUpdatedValuesArray)) {
                            $objDatabase = Asset::GetDatabase();
                            foreach ($strUpdatedValuesArray as $query) {
                                $objDatabase->NonQuery($query);
                            }
                            foreach ($this->objUpdatedItemArray as $intItemKey => $objUpdatedItem) {
                                if (isset($strUpdatedItemCFVArray[$intItemKey]) && count($strUpdatedItemCFVArray[$intItemKey])) {
                                    $strCFVArray = array();
                                    foreach ($arrAssetCustomField as $objCustomField) {
                                        $strCFVArray[] = sprintf("`cfv_%s`=%s", $objCustomField->CustomFieldId, $strUpdatedItemCFVArray[$intItemKey][$objCustomField->CustomFieldId]);
                                    }
                                    if (isset($strCFVArray) && count($strCFVArray)) {
                                        $strQuery = sprintf("UPDATE `asset_custom_field_helper` SET %s WHERE `asset_id`='%s'", str_replace('""', '"', implode(", ", $strCFVArray)), $intItemKey);
                                        $objDatabase->NonQuery($strQuery);
                                        // insert nulls where not allowed
                                        $theAsset = Asset::Load($intItemKey);
                                        $strQuery = $this->substactNotAllowedFields($theAsset->AssetModelId, $theAsset->AssetId);
                                        if ($strQuery) {
                                            $objDatabase->NonQuery($strQuery);
                                        }
                                    }
                                }
                            }
                        }

                        //}
                        //$this->intCurrentFile++;
                        //break;
                    }
                    //$j++;
                }

                if ($this->intImportStep == 3) {
                    /*if (count($this->strSelectedValueArray)) {
                      $objDatabase = CustomField::GetDatabase();
                      $strQuery = sprintf("INSERT INTO `custom_field_selection` " .
                                          "(`entity_id`,`entity_qtype_id`, `custom_field_value_id`) " .
                                          "VALUES %s;", implode(", ", $this->strSelectedValueArray));
                      $objDatabase->NonQuery($strQuery);
                    }*/

                    // Insert Values into helper tables
                    $objDatabase = Asset::GetDatabase();
                    $objDatabase->NonQuery("SET FOREIGN_KEY_CHECKS=0;");
                    // Insert into asset_custom_field_helper
                    $objDatabase->NonQuery(sprintf("INSERT INTO `asset_custom_field_helper` (`asset_id`) (SELECT `asset_id` FROM `asset` WHERE `asset_id` NOT IN (SELECT `asset_id` FROM `asset_custom_field_helper`));"));
                    // Inserts end
                    $objDatabase->NonQuery("SET FOREIGN_KEY_CHECKS=1;");

                    $this->blnImportEnd = true;
                    $this->btnNext->Warning = "";

                    $this->lblImportResults->Display = true;
                    if (count($this->objNewAssetArray)) {
                        //$this->lblImportAssets->Display = true;
                        $this->dtgAsset->Paginator = new QPaginator($this->dtgAsset);
                        $this->dtgAsset->ItemsPerPage = 20;

                    }
                    if (count($this->objUpdatedAssetArray)) {
                        $this->lblImportUpdatedAssets->Display = true;
                        $this->dtgUpdatedAsset->Paginator = new QPaginator($this->dtgUpdatedAsset);
                        $this->dtgUpdatedAsset->ItemsPerPage = 20;

                    }
                    if (count($this->objNewAssetArray)) {
                        $this->lblImportAsset->Display = true;
                        $this->dtgAsset->Paginator = new QPaginator($this->dtgAsset);
                        $this->dtgAsset->ItemsPerPage = 20;
                    }
                    $this->btnNext->Display = false;
                    $this->btnCancel->Display = false;
                    $this->btnUndoLastImport->Display = true;
                    $this->btnImportMore->Display = true;
                    $this->btnReturnToAssets->Display = true;
                    $this->lblImportSuccess->Display = true;
                    $this->lblImportSuccess->Text = sprintf("Success:<br/>" .
                        "<b>%s</b> Records imported successfully<br/>" .
                        "<b>%s</b> Records skipped due to error<br/>", count($this->objNewAssetArray) + count($this->objUpdatedItemArray), $this->intSkippedRecordCount);
                    if ($this->intSkippedRecordCount) {
                        $this->lblImportSuccess->Text .= sprintf("<a href='./asset_import.php?intDownloadCsv=1'>Click here to download records that could not be imported</a>");
                    }
                    $this->lblImportSuccess->Text .= "<br/><br/>";
                    $this->intImportStep = -1;
                }
                // Enable Next button
                $this->btnNext->Enabled = true;
                if (!$this->blnImportEnd && !$this->intCurrentFile) {
                    $this->intImportStep++;
                }
            }
            fclose($file_skipped);
        }
        if (!$blnError) {
            if (($this->blnImportEnd || $this->intImportStep == 2) && $this->intImportStep != -1) {
                $this->intStep++;
                $this->DisplayStepForm($this->intStep);
            }
            if (!$this->blnImportEnd) {
                $this->btnNext->Warning = "Please wait...";
                QApplication::ExecuteJavaScript("document.getElementById('" . $this->btnNext->ControlId . "').click();");
            }
            if (!($this->intCurrentFile < count($this->strFilePathArray))) {
                $this->intCurrentFile = 0;
                $this->intImportStep++;
            }
        }
    }

    public function return_array_chunk($dtgDatagrid, $strArray)
    {
        if (count($strArray) > 0) {
            $intPageNumber = $dtgDatagrid->Paginator->PageNumber;
            $arrChunks = array_chunk($strArray, 20);
            $intKey = $intPageNumber - 1;
            return $arrChunks[$intKey];
        } else {
            return false;
        }
    }

    // Case-insensitive in array function
    protected function in_array_nocase($search, &$array)
    {
        $search = strtolower($search);
        foreach ($array as $item)
            if (strtolower($item) == $search)
                return TRUE;
        return FALSE;
    }

    protected function lstMapHeader_Create($objParentObject, $intId, $strName = null)
    {
        if ($this->chkHeaderRow->Checked && !$strName) {
            $strName = 'abcdefg';
        }
        $strName = strtolower($strName);
        $lstMapHeader = new QListBox($objParentObject);
        $lstMapHeader->Name = "lst" . $intId;
        $strAssetGroup = "Asset";
        $strAssetGroup = "Asset";
        $lstMapHeader->AddItem("- Not Mapped -", null);
        // Add ID for update imports only
        if ($this->lstImportAction->SelectedValue == 2) {
            $lstMapHeader->AddItem("ID", "ID", ($strName == 'id') ? true : false, $strAssetGroup, 'CssClass="redtext"');
        }
        $lstMapHeader->AddItem("Asset Tag", "Asset Tag", ($strName == 'asset tag') ? true : false, $strAssetGroup, 'CssClass="redtext"');
        $lstMapHeader->AddItem("Model", "Model", ($strName == 'model') ? true : false, $strAssetGroup, 'CssClass="redtext"');
        $lstMapHeader->AddItem("Location", "Location", ($strName == 'location') ? true : false, $strAssetGroup, 'CssClass="redtext"');
        $lstMapHeader->AddItem("Parent Asset", "Parent Asset", ($strName == 'parent asset') ? true : false, $strAssetGroup);
        $lstMapHeader->AddItem("Locked To Parent", "Locked To Parent", ($strName == 'locked to parent') ? true : false, $strAssetGroup);
        foreach ($this->arrAssetCustomField as $objCustomField) {
            // Add style to Required Fields for All Asset Models
            if ($objCustomField->RequiredFlag && $objCustomField->AllAssetModelsFlag) {
                $lstMapHeader->AddItem($objCustomField->ShortDescription, "asset_" . $objCustomField->CustomFieldId, ($strName == strtolower($objCustomField->ShortDescription)) ? true : false, $strAssetGroup, 'CssClass="redtext"');

            } else {
                $lstMapHeader->AddItem($objCustomField->ShortDescription, "asset_" . $objCustomField->CustomFieldId, ($strName == strtolower($objCustomField->ShortDescription)) ? true : false, $strAssetGroup);
            }
        }
        /*$lstMapHeader->AddItem("Created By", "Created By", ($strName == 'created by') ? true : false, $strAssetGroup);
        $lstMapHeader->AddItem("Created Date", "Created Date", ($strName == 'created date') ? true : false, $strAssetGroup);
        $lstMapHeader->AddItem("Modified By", "Modified By", ($strName == 'modified by') ? true : false, $strAssetGroup);
        $lstMapHeader->AddItem("Modified Date", "Modified Date", ($strName == 'modified date') ? true : false, $strAssetGroup);*/
        $lstMapHeader->AddAction(new QChangeEvent(), new QAjaxAction('lstTramorField_Change'));
        $this->lstMapHeaderArray[] = $lstMapHeader;
        /*if ($strName && $lstMapHeader->SelectedValue) {
          //$this->arrTracmorField[$intId] = strtolower($lstMapHeader->SelectedValue);
      }*/
        return true;
    }

    protected function lstTramorField_Change($strFormId, $strControlId, $strParameter)
    {
        $objControl = QForm::GetControl($strControlId);
        if ($objControl->SelectedValue != null) {
            $search = strtolower($objControl->SelectedValue);
            if ($this->in_array_nocase($search, $this->arrTracmorField)) {
                $objControl->Warning = "This value has already been selected.";
                $objControl->SelectedIndex = 0;
                unset($this->arrTracmorField[substr($objControl->Name, 3)]);
            } else {
                $objControl->Warning = "";
                $txtDefault = $this->txtMapDefaultValueArray[substr($objControl->Name, 3)];
                $lstDefault = $this->lstMapDefaultValueArray[substr($objControl->Name, 3)];
                $dtpDefault = $this->dtpDateArray[substr($objControl->Name, 3)];
                $this->arrTracmorField[substr($objControl->Name, 3)] = $search;
                if (substr($objControl->SelectedValue, 0, 6) == "model_" || substr($objControl->SelectedValue, 0, 6) == "asset_") {
                    $objCustomField = CustomField::LoadByCustomFieldId(substr($objControl->SelectedValue, 6));
                    // type = Text or TextBox
                    if ($objCustomField->CustomFieldQtypeId != 2) {
                        $txtDefault->Required = $objCustomField->RequiredFlag;
                        // If it is a required text field, then assign the default text for a new entity only
                        if ($objCustomField->RequiredFlag && $objCustomField->DefaultCustomFieldValueId) {
                            $txtDefault->Text = $objCustomField->DefaultCustomFieldValue->ShortDescription;
                        } else {
                            $txtDefault->Text = "";
                        }
                        $txtDefault->Display = true;
                        $lstDefault->Display = false;
                        $dtpDefault->Display = false;
                    } // type = Select
                    else {
                        $lstDefault->RemoveAllItems();
                        $lstDefault->AddItem('- Select One -', null);
                        $lstDefault->Required = true;

                        $objCustomFieldValueArray = CustomFieldValue::LoadArrayByCustomFieldId(substr($objControl->SelectedValue, 6), QQ::Clause(QQ::OrderBy(QQN::CustomFieldValue()->ShortDescription)));
                        if ($objCustomFieldValueArray) {
                            foreach ($objCustomFieldValueArray as $objCustomFieldValue) {
                                $objListItem = new QListItem($objCustomFieldValue->__toString(), $objCustomFieldValue->CustomFieldValueId);
                                // If it is a required field, then select the value on new entities by default
                                if ($objCustomField->RequiredFlag && $objCustomField->DefaultCustomFieldValueId && $objCustomField->DefaultCustomFieldValueId == $objCustomFieldValue->CustomFieldValueId) {
                                    $objListItem->Selected = true;
                                }
                                $lstDefault->AddItem($objListItem);
                            }
                        }

                        $txtDefault->Display = false;
                        $lstDefault->Display = true;
                        $dtpDefault->Display = false;
                    }
                } elseif ($objControl->SelectedValue == "Created By" || $objControl->SelectedValue == "Modified By") {
                    $lstDefault->RemoveAllItems();
                    foreach ($this->intUserArray as $strUsername => $intUserId) {
                        $objListItem = new QListItem($strUsername, $intUserId, ($intUserId != $_SESSION['intUserAccountId']) ? false : true);
                        $lstDefault->AddItem($objListItem);
                    }
                    $txtDefault->Display = false;
                    $lstDefault->Display = true;
                    $dtpDefault->Display = false;
                } elseif ($objControl->SelectedValue == "Asset Tag") {
                    $txtDefault->Display = false;
                    $lstDefault->Display = false;
                    $dtpDefault->Display = false;
                } elseif ($objControl->SelectedValue == "Created Date" || $objControl->SelectedValue == "Modified Date") {
                    $txtDefault->Display = false;
                    $lstDefault->Display = false;
                    $dtpDefault->Display = true;
                } else {
                    $txtDefault->Display = true;
                    $lstDefault->Display = false;
                    $dtpDefault->Display = false;
                }
            }
        } else {
            unset($this->arrTracmorField[substr($objControl->Name, 3)]);
        }
    }

    protected function GetCsvSettings()
    {
        switch ($this->lstFieldSeparator->SelectedValue) {
            case 1:
                $strSeparator = ",";
                break;
            case 2:
                $strSeparator = "\t";
                break;
            default:
                $strSeparator = $this->txtFieldSeparator->Text;
                break;
        }
        switch ($this->lstTextDelimiter->SelectedValue) {
            case 1:
                $strDelimiter = '"';
                break;
            case 2:
                $strDelimiter = "'";
                break;
            case 3:
                $strDelimiter = '"';
                break;
            default:
                $strDelimiter = $this->txtTextDelimiter->Text;
                break;
        }
        return $settings = array(
            'delimiter' => $strSeparator,
            'eol' => "\n",
            'length' => 9999,
            'escape' => $strDelimiter
        );
    }

    protected function DisplayStepForm($intStep)
    {
        switch ($intStep) {
            case 1:
                $this->pnlMain->RemoveChildControls($this->pnlMain);
                $this->pnlStepOne_Create();
                break;
            case 2:
                $this->pnlMain->RemoveChildControls($this->pnlMain);
                $this->pnlStepTwo_Create();
                break;
            case 3:
                $this->pnlMain->RemoveChildControls($this->pnlMain);
                $this->pnlStepThree_Create();
                break;
            case 4:
                $this->DisplayStepForm(1);
                $this->intStep = 1;
                break;
        }
    }

    // Cancel button click action
    protected function btnCancel_Click()
    {
        $this->UndoImport();
        QApplication::Redirect("./asset_import.php");
    }

    protected function btnImportMore_Click()
    {
        QApplication::Redirect("./asset_import.php");
    }

    protected function btnReturnToAssets_Click()
    {
        QApplication::Redirect("../assets/asset_list.php");
    }

    // Delete All imported Assets, Asset Models, Manufacturers, Categories and Locations
    protected function UndoImport()
    {
        $objDatabase = Asset::GetDatabase();
        //$strQuery = "SET FOREIGN_KEY_CHECKS=0;";
        //$objDatabase->NonQuery($strQuery);
        if (isset($this->arrOldItemArray)) {
            $strQuery = "SET FOREIGN_KEY_CHECKS=0;";
            $objDatabase->NonQuery($strQuery);
            foreach ($this->arrOldItemArray as /*$intItemId => */
                     $objOldItem) {
                $objDatabase->NonQuery($objOldItem['ItemSql']);
                if ($objOldItem['CFVSql'])
                    $objDatabase->NonQuery($objOldItem['CFVSql']);
            }
            $strQuery = "SET FOREIGN_KEY_CHECKS=1;";
            $objDatabase->NonQuery($strQuery);
        }
        //$strQuery = "SET FOREIGN_KEY_CHECKS=0;";
        //$objDatabase->NonQuery($strQuery);
        if (count($this->objNewAssetArray)) {
            $strQuery = sprintf("DELETE FROM `asset` WHERE `asset_id` IN (%s)", implode(", ", array_keys($this->objNewAssetArray)));
            $objDatabase->NonQuery($strQuery);
            // Do not need to delete it manually (automatically CASCADE deletion)
            //$strQuery = sprintf("DELETE FROM `asset_custom_field_helper` WHERE `asset_id` IN (%s)", implode(", ", array_keys($this->objNewAssetArray)));
            //$objDatabase->NonQuery($strQuery);
        }
        //$strQuery = "SET FOREIGN_KEY_CHECKS=1;";
        //$objDatabase->NonQuery($strQuery);
    }

    protected function PutSkippedRecordInFile($file, $strRowArray)
    {
        fputcsv($file, $strRowArray, $this->FileCsvData->settings['delimiter'], $this->FileCsvData->settings['escape']);
    }

    protected function btnAddField_Click()
    {
        $intTotalCount = count($this->lstMapHeaderArray);
        $this->lstMapHeader_Create($this, $intTotalCount - 1, ($this->chkHeaderRow->Checked) ? "addfield" : null);
        $objTemp = $this->lstMapHeaderArray[$intTotalCount];
        $this->lstMapHeaderArray[$intTotalCount] = $this->lstMapHeaderArray[$intTotalCount - 1];
        $this->lstMapHeaderArray[$intTotalCount - 1] = $objTemp;
        $txtDefaultValue = new QTextBox($this);
        $txtDefaultValue->Width = 200;
        $this->txtMapDefaultValueArray[] = $txtDefaultValue;
        $lstDefaultValue = new QListBox($this);
        $lstDefaultValue->Width = 200;
        $lstDefaultValue->Display = false;
        $this->lstMapDefaultValueArray[] = $lstDefaultValue;
        $dtpDate = new QDateTimePicker($this);
        $dtpDate->DateTimePickerType = QDateTimePickerType::Date;
        $dtpDate->DateTimePickerFormat = QDateTimePickerFormat::MonthDayYear;
        $dtpDate->Display = false;
        $this->dtpDateArray[] = $dtpDate;

        $btnRemove = new QButton($this);
        $btnRemove->Text = "Remove";
        $btnRemove->ActionParameter = $intTotalCount - 1;
        $btnRemove->AddAction(new QClickEvent(), new QServerAction('btnRemove_Click'));
        $btnRemove->AddAction(new QEnterKeyEvent(), new QServerAction('btnRemove_Click'));
        $btnRemove->AddAction(new QEnterKeyEvent(), new QTerminateAction());

        if (isset($this->arrMapFields[$intTotalCount - 1])) {
            unset($this->arrMapFields[$intTotalCount - 1]);
        }

        $this->btnRemoveArray[$intTotalCount - 1] = $btnRemove;
    }

    protected function btnRemove_Click($strFormId, $strControlId, $strParameter)
    {
        $intId = (int)$strParameter;
        $intTotalCount = count($this->lstMapHeaderArray) - 1;

        if ($intId < count($this->lstMapHeaderArray) - 2) {
            for ($i = $intId; $i < count($this->lstMapHeaderArray) - 1; $i++) {
                $this->lstMapHeaderArray[$i] = $this->lstMapHeaderArray[$i + 1];
                if (isset($this->txtMapDefaultValueArray[$i + 1])) {
                    $this->txtMapDefaultValueArray[$i] = $this->txtMapDefaultValueArray[$i + 1];
                    $this->lstMapDefaultValueArray[$i] = $this->lstMapDefaultValueArray[$i + 1];
                    $this->dtpDateArray[$i] = $this->dtpDateArray[$i + 1];
                    $this->btnRemoveArray[$i] = $this->btnRemoveArray[$i + 1];
                    $this->btnRemoveArray[$i]->ActionParameter = $i;
                } else {
                    unset($this->txtMapDefaultValueArray[$i]);
                    unset($this->lstMapDefaultValueArray[$i]);
                    unset($this->dtpDateArray[$i]);
                    unset($this->btnRemoveArray[$i]);
                }
            }
            unset($this->lstMapHeaderArray[$intTotalCount]);
        } else {
            $this->lstMapHeaderArray[$intId] = $this->lstMapHeaderArray[$intId + 1];
            unset($this->lstMapHeaderArray[$intId + 1]);
            unset($this->txtMapDefaultValueArray[$intId]);
            unset($this->lstMapDefaultValueArray[$intId]);
            unset($this->dtpDateArray[$intId]);
            unset($this->btnRemoveArray[$intId]);
        }
    }

    //Returns array of custom field ids not allowed for asset with defined Asset Model
    // or query sting wich sets to null not allowed fields
    protected function substactNotAllowedFields($intAssetModelId, $intCustomFieldId = null)
    {
        $arrAllowed = array();
        foreach (AssetCustomFieldAssetModel::LoadArrayByAssetModelId($intAssetModelId) as $objAssetCustomField) {
            $arrAllowed[] = $objAssetCustomField->CustomFieldId;
        }
        $arrToClear = array();
        foreach (EntityQtypeCustomField::LoadArrayByEntityQtypeId(1) as $objAssetCustomField) {
            if (!in_array($objAssetCustomField->CustomFieldId, $arrAllowed) && $objAssetCustomField->CustomField->AllAssetModelsFlag != 1) {
                $arrToClear[] = $objAssetCustomField->CustomFieldId;
            }
        }
        if ($intCustomFieldId) {
            if ($arrToClear && count($arrToClear) > 0) {
                foreach ($arrToClear as $idToBeNull) {
                    $arrForQuery[] = sprintf("`cfv_%s`= NULL", $idToBeNull);
                }
                return sprintf("UPDATE `asset_custom_field_helper` SET %s WHERE `asset_id`='%s'", str_replace('""', '"', implode(", ", $arrForQuery)), $intCustomFieldId);
            }
        } else {
            return $arrToClear;
        }
    }

    public function getLstKeys()
    {
        $arrToReturn = array();
        foreach ($this->lstMapHeaderArray as $item) {
            if (get_class($item) == 'QListBox') {
                if (is_numeric(str_replace('asset_', '', $item->SelectedValue))) {
                    $arrToReturn[] = CustomField::Load(str_replace('asset_', '', $item->SelectedValue))->ShortDescription;
                } else {
                    $arrToReturn[] = $item->SelectedValue;
                }
            }
        }
        return $arrToReturn;
    }

}

// Go ahead and run this form object to generate the page
AssetImportForm::Run('AssetImportForm', 'asset_import.tpl.php');
?>
