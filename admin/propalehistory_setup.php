<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/doc2project.php
 * 	\ingroup	doc2project
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */

// Dolibarr environment
require '../config.php';
// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/propalehistory.lib.php';
dol_include_once('propalehistory/class/propalehistory.class.php');

// Translations
$langs->load("propalehistory@propalehistory");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

if(!class_exists('FormSetup')){
	// une Pr est en cour pour fixer certains elements de la class en V16 (car c'est des fix/new)
	if (versioncompare(explode('.' , DOL_VERSION), array(16)) < 0 && !class_exists('FormSetup')){
		require_once __DIR__.'/../backport/v16/core/class/html.formsetup.class.php';
	} else {
		require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
	}
}


$formSetup = new FormSetup($db);


// Archiver automatiquement une proposition commerciale lors de sa validation
$formSetup->newItem('PROPALEHISTORY_AUTO_ARCHIVE')->setAsYesNo()->nameText = $langs->trans("AutoArchive");

// Afficher le numéro de version sur le PDF (à partir de la 2e)
$formSetup->newItem('PROPALEHISTORY_SHOW_VERSION_PDF')->setAsYesNo();

// Masquer le numéro de version dans les onglets
$formSetup->newItem('PROPALEHISTORY_HIDE_VERSION_ON_TABS')->setAsYesNo();

// Archiver aussi le PDF
$formSetup->newItem('PROPALEHISTORY_ARCHIVE_PDF_TOO')->setAsYesNo();

// Demander la création d'un nouvelle version à la modification d'une proposition
$formSetup->newItem('PROPALEHISTORY_ARCHIVE_ON_MODIFY')->setAsYesNo();

// new version is created with date now by default (archive button or archive on modify)
$formSetup->newItem('PROPALEHISTORY_ARCHIVE_WITH_DATE_NOW')->setAsYesNo();

$formSetup->newItem('EXPERIMENTAL_OPTIONS')->setAsTitle();

// Reset des dates des devis sur lors de l'archivage à la date du jour
$formSetup->newItem('PROPALEHISTORY_ARCHIVE_AND_RESET_DATES')->setAsYesNo();

// keep version number on restoring
$formSetup->newItem('PROPALEHISTORY_RESTORE_KEEP_VERSION_NUM')->setAsYesNo()->helpText = $langs->transnoentities('PropaleHistoryRestoreKeepVersionNumHelp');

/*
 * Actions
 */
if ($action == 'update' && !empty($formSetup) && is_object($formSetup) && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
	header('Location:'.$_SERVER['PHP_SELF']);
	exit;
}

/*
 * View
 */
$page_name = "PropalHistory";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans($page_name), $linkback);

// Configuration header
$head = propalehistoryAdminPrepareHead();
print dol_get_fiche_head(
    $head,
    'settings',
    $langs->trans("Module1040900Name"),
    0,
    "project"
);

print dol_get_fiche_end();


if ($action == 'edit') {
	print $formSetup->generateOutput(true);
	print '<br>';
} else {
	if (!empty($formSetup->items)) {
		print $formSetup->generateOutput();

		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
		print '</div>';
	}
	else {
		print '<br>'.$langs->trans("NothingToSetup");
	}
}


llxFooter();

$db->close();
