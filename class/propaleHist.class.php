<?php
	class TPropaleHist extends TObjetStd {

		function __construct() {
			parent::set_table(MAIN_DB_PREFIX.'propale_history');
			parent::add_champs('serialized_parent_propale','type=text;index');
			parent::add_champs('fk_propale','type=entier;index');
			parent::add_champs('date_version','type=date;');
			parent::add_champs('total','type=float;');
			parent::start();
			parent::_init_vars();
		}

		function save(&$db) {
			parent::save($db);
		}

		function load(&$ATMdb,$idVersion, $loadChild = true){
			parent::load($ATMdb,$idVersion, $loadChild);
		}

		function delete(&$ATMdb) {
			parent::delete($ATMdb);
		}

		function getObject() {

			$propal = unserialize($this->serialized_parent_propale);
		        if($propal === false) $propal = unserialize(utf8_decode($this->serialized_parent_propale));

			return $propal;
		}

		static function archiverPropale(&$ATMdb, &$object)
		{
			global $conf, $langs;

			if (!empty($conf->global->PROPALEHISTORY_ARCHIVE_PDF_TOO)) {
				TPropaleHist::archivePDF($object);				
			}

			$newVersionPropale = new TPropaleHist;

			$newVersionPropale->serialized_parent_propale = serialize($object);
			$newVersionPropale->date_version = dol_now();
			$newVersionPropale->fk_propale = $object->id;
			$newVersionPropale->total = $object->total_ht;

			$newVersionPropale->save($ATMdb);
			?>
				<script language="javascript">
					document.location.href="<?php echo $_SERVER['PHP_SELF'] ?>?id=<?php echo $_REQUEST['id']?>&mesg=<?php echo $langs->transnoentities('HistoryVersionSuccessfullArchived') ?>";
				</script>
			<?php


			/*if($_REQUEST['actionATM'] == 'createVersion') {
				setEventMessage('Version sauvegardée avec succès.', 'mesgs');
			}*/

		}

		static function archivePDF(&$object)
		{
			global $db;

			$sql.= " SELECT count(*) as nb";
			$sql.= " FROM ".MAIN_DB_PREFIX."propale_history";
			$sql.= " WHERE fk_propale = ".$object->id;
			$resql = $db->query($sql);

			$nb=1;
			if ($resql && ($row = $db->fetch_object($resql))) $nb = $row->nb + 1;

			$ok = 1;
			if ($object->entity > 1) {
				$filename = DOL_DATA_ROOT . '/' . $object->entity . '/propale/' . $object->ref . '/' .$object->ref;
				$path = DOL_DATA_ROOT . '/' . $object->entity . '/propale/' . $object->ref . '/' .$object->ref . '.pdf';
			}
			else {
				$filename = DOL_DATA_ROOT . '/propale/' . $object->ref . '/' .$object->ref;
				$path = DOL_DATA_ROOT . '/propale/' . $object->ref . '/' .$object->ref . '.pdf';
			}

			if (!is_file($path)) $ok = TPropaleHist::generatePDF($object);

			if ($ok > 0)
			{
				exec('cp "'.$path.'" "'.$filename.'-'.$nb.'.pdf"');
			}
		}

		static function generatePDF(&$object)
		{
			global $conf,$langs;

			return $object->generateDocument($conf->global->PROPALE_ADDON_PDF, $langs, 0, 0, 0);
		}

		static function restaurerPropale(&$ATMdb, &$object) {

			global $db, $user,$langs;

			$versionPropale = new TPropaleHist;
			$versionPropale->load($ATMdb, $_REQUEST['idVersion']);

			$propale = $versionPropale->getObject();

			$propale->statut = 0;
			$object->statut = 0;

			foreach($object->lines as $line) {

				$object->deleteline($line->rowid)."<br />";

			}

			foreach($propale->lines as $line) {

				$object->addline(
					$line->desc,
					$line->subprice,
					$line->qty,
					$line->tva_tx,
					$line->localtax1_tx,
					$line->localtax2_tx,
					$line->fk_product,
					$line->remise_percent,
					'HT',
					$line->subprice,
					$line->info_bits,
					$line->product_type,
					$line->rang,
					$line->special_code,
					$line->fk_parent_line,
					$line->fk_fournprice,
					$line->pa_ht,
					$line->label,
					$line->date_start,
					$line->date_end,
					$line->array_options
				);

			}

			$object->set_draft($user); // Pour pouvoir modifier les dates, le statut doit être à 0
			$object->set_availability($user, $propale->availability_id);
			$object->set_date($user, $propale->date);
			$object->set_date_livraison($user, $propale->date_livraison);
			$object->set_echeance($user, $propale->fin_validite);
			$object->set_ref_client($user, $propale->ref_client);
			$object->set_demand_reason($user, $propale->demand_reason_id);
			$object->setPaymentMethods($propale->mode_reglement_id);
			$object->setPaymentTerms($propale->cond_reglement_id);
			$object->valid($user);

			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$_REQUEST['id'].'&mesg='.$langs->transnoentities('HistoryVersionSuccessfullRestored'));

		}

		static function getVersions(&$db, $fk_object) {

			$sql.= " SELECT rowid, date_version, date_cre, total";
			$sql.= " FROM ".MAIN_DB_PREFIX."propale_history";
			$sql.= " WHERE fk_propale = ".$fk_object;
			$sql.= " ORDER BY rowid ASC";
			$resql = $db->query($sql);

			$TVersion = array();

			if($resql) {

				$num = $db->num_rows($resql);
				while($row = $db->fetch_object($resql)) {
					$TVersion[] = $row;
				}

			}

			return $TVersion;
		}

		static function listeVersions(&$db, $object) {
			global $langs,$conf;
			$TVersion = self::getVersions($db, $object->id);


			$num = count($TVersion);

			$url=DOL_URL_ROOT.'/comm/propal.php';
			if ((float) DOL_VERSION >= 4.0) {
			    $url=DOL_URL_ROOT.'/comm/propal/card.php';
			}
			
			if($num>0) {

				print '<div id="formListe" style="clear:both; margin:15px 0">';
				print '<form name="formVoirPropale" method="POST" action="'.$url.'?id='.GETPOST('id','int').'">';
				print '<input type="hidden" name="actionATM" value="viewVersion" />';
				print '<input type="hidden" name="socid" value="'.$object->socid.'" />';
				print '<select name="idVersion">';
				$i = 1;

				foreach($TVersion as &$row){

					if(isset($_REQUEST['idVersion']) && $_REQUEST['idVersion'] == $row->rowid){
						$selected = 'selected="selected"';
					} else {
						$selected = "";
					}
					
					print '<option id="'.$row->rowid.'" value="'.$row->rowid.'" '.$selected.'>Version n° '.$i.' - '.price($row->total).' '.$langs->getCurrencySymbol($conf->currency,0).' - '.dol_print_date($db->jdate($row->date_cre), "dayhour").'</option>';

					$i++;

				}

				print '</select>';
				print '<input class="butAction" id="voir" value="'.$langs->trans('Visualiser').'" type="SUBMIT" />';
				print '</form>';
				print '</div>';

				?>
				<script type="text/javascript">
					$(document).ready(function(){
						$("#formListe").appendTo('div.tabsAction');
					})
				</script>
				<?php

			}
			else{

				null;
			}




			return $num;
		}

	}

	class PropalHist extends Propal {

		  function __construct($db, $socid="")
		    {
		        global $conf,$langs;

		        $this->db = $db;
		        $this->socid = $socid;

		        $this->products = array();
		        $this->remise = 0;
		        $this->remise_percent = 0;
		        $this->remise_absolue = 0;

		        $this->duree_validite=$conf->global->PROPALE_VALIDITY_DURATION;

		        $langs->load("propal");
		        $this->labelstatut[0]=(! empty($conf->global->PROPAL_STATUS_DRAFT_LABEL) ? $conf->global->PROPAL_STATUS_DRAFT_LABEL : $langs->trans("PropalStatusDraft"));
		        $this->labelstatut[1]=(! empty($conf->global->PROPAL_STATUS_VALIDATED_LABEL) ? $conf->global->PROPAL_STATUS_VALIDATED_LABEL : $langs->trans("PropalStatusValidated"));
		        $this->labelstatut[2]=(! empty($conf->global->PROPAL_STATUS_SIGNED_LABEL) ? $conf->global->PROPAL_STATUS_SIGNED_LABEL : $langs->trans("PropalStatusSigned"));
		        $this->labelstatut[3]=(! empty($conf->global->PROPAL_STATUS_NOTSIGNED_LABEL) ? $conf->global->PROPAL_STATUS_NOTSIGNED_LABEL : $langs->trans("PropalStatusNotSigned"));
		        $this->labelstatut[4]=(! empty($conf->global->PROPAL_STATUS_BILLED_LABEL) ? $conf->global->PROPAL_STATUS_BILLED_LABEL : $langs->trans("PropalStatusBilled"));
		        $this->labelstatut_short[0]=(! empty($conf->global->PROPAL_STATUS_DRAFTSHORT_LABEL) ? $conf->global->PROPAL_STATUS_DRAFTSHORT_LABEL : $langs->trans("PropalStatusDraftShort"));
		        $this->labelstatut_short[1]=(! empty($conf->global->PROPAL_STATUS_VALIDATEDSHORT_LABEL) ? $conf->global->PROPAL_STATUS_VALIDATEDSHORT_LABEL : $langs->trans("Opened"));
		        $this->labelstatut_short[2]=(! empty($conf->global->PROPAL_STATUS_SIGNEDSHORT_LABEL) ? $conf->global->PROPAL_STATUS_SIGNEDSHORT_LABEL : $langs->trans("PropalStatusSignedShort"));
		        $this->labelstatut_short[3]=(! empty($conf->global->PROPAL_STATUS_NOTSIGNEDSHORT_LABEL) ? $conf->global->PROPAL_STATUS_NOTSIGNEDSHORT_LABEL : $langs->trans("PropalStatusNotSignedShort"));
		        $this->labelstatut_short[4]=(! empty($conf->global->PROPAL_STATUS_BILLEDSHORT_LABEL) ? $conf->global->PROPAL_STATUS_BILLEDSHORT_LABEL : $langs->trans("PropalStatusBilledShort"));
		    }

			function getLinesArray()
    		{
    			null;
			}

	}
