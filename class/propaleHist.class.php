<?php
	class TPropaleHist extends TObjetStd {

        /**
         * @var array options
         */
        public $array_options = array();

		function __construct() {
			parent::set_table(MAIN_DB_PREFIX.'propale_history');
			parent::add_champs('serialized_parent_propale',array('type'=>'text'));
			parent::add_champs('fk_propale',array('type'=>'integer','index'=>true));
			parent::add_champs('date_version',array('type'=>'date'));
			parent::add_champs('total',array('type'=>'float'));
			parent::start();
			parent::_init_vars();
		}

		function save(&$PDOdb) {

		//	$PDOdb->debug =true;
			parent::save($PDOdb);
		}

		function load(&$PDOdb,$idVersion, $loadChild = true){

			parent::load($PDOdb,$idVersion, $loadChild);
		}

		function delete(&$PDOdb) {
			parent::delete($PDOdb);
		}

		public function setObject(&$object) {
			global $conf;

			$code = base64_encode(gzdeflate(serialize($object)));

			$this->serialized_parent_propale = $code;

		}

		function getObject() {
			$code = gzinflate(base64_decode($this->serialized_parent_propale));
			if($code === false) {
				$code = $this->serialized_parent_propale;
			}

			$propal = unserialize($code);
			if($propal === false) $propal = unserialize(utf8_decode($code));

			return $propal;
		}

        /**
         * Archive proposal
         *
         * @param   TPDOdb  $PDOdb      PDO connection
         * @param   Propal  $object     Proposal object
         * @return  int     <0 if KO, >0 if OK
         */
		static function archiverPropale(&$PDOdb, &$object)
		{
			global $conf, $db, $langs;

			if (!empty($conf->global->PROPALEHISTORY_ARCHIVE_PDF_TOO)) {
				TPropaleHist::archivePDF($object);
			}

            // set proposal version number before saving
            $update_extras = false;
            if (!empty($conf->global->PROPALEHISTORY_RESTORE_KEEP_VERSION_NUM)) {
                $object->array_options['options_propalehistory_version_num'] = self::getVersionNumNext($db, $object->id); // get next version number
                $update_extras = true;
            } else {
                if (!empty($object->array_options['options_propalehistory_version_num'])) {
                    $object->array_options['options_propalehistory_version_num'] = null; // reset version number
                    $update_extras = true;
                }
            }
            if ($update_extras === true) {
                $res = $object->insertExtraFields();
                if ($res < 0) {
                    return -1;
                }
            }

            $error = 0;

			$newVersionPropale = new TPropaleHist;
			$newVersionPropale->setObject($object);

			$newVersionPropale->date_version = dol_now();
			$newVersionPropale->fk_propale = $object->id;
			$newVersionPropale->total = $object->total_ht;

			$newVersionPropale->save($PDOdb);

            if (!empty($conf->global->PROPALEHISTORY_ARCHIVE_AND_RESET_DATES) && $object->id > 0) {
                $now = dol_now();
                $fin_validite = $now + ($object->duree_validite * 24 * 3600);

                $db->begin();

                $sql  = "UPDATE " . MAIN_DB_PREFIX . "propal";
                $sql .= " SET datep = '" . $db->idate($now) . "'";
                $sql .= ", fin_validite = '" . $db->idate($fin_validite) . "'";
                $sql .= " WHERE rowid = " . $object->id;

                dol_syslog(__METHOD__, LOG_DEBUG);
                $resql = $db->query($sql);
                if (!$resql) {
                    $error++;
                    $object->error = $db->lasterror();
                    $object->errors[] = $object->error;
                    $db->rollback();
                    dol_syslog(__METHOD__ . ' Error : ' . $object->error, LOG_ERR);
                } else {
                    $db->commit();
                }

                if (!$error && empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE)) {
                    // reload the object with new lines
                    $ret = $object->fetch($object->id);
                    $ret = $object->fetch_thirdparty($object->socid);

                    // Define output language
                    $outputlangs = $langs;
                    if (!empty($conf->global->MAIN_MULTILANGS)) {
                        $outputlangs = new Translate('', $conf);
                        $newlang = (GETPOST('lang_id', 'aZ09') ? GETPOST('lang_id', 'aZ09') : $object->thirdparty->default_lang);
                        $outputlangs->setDefaultLang($newlang);
                    }

                    // PDF
                    $hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
                    $hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
                    $hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);

                    $object->generateDocument($object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
                }
            }

            // update to next version to work on (only if we work on last version)
            if (!$error && !empty($conf->global->PROPALEHISTORY_RESTORE_KEEP_VERSION_NUM)) {
                $object->array_options['options_propalehistory_version_num']++;
                $res = $object->insertExtraFields();
                if ($res < 0) {
                    $error++;
                }
            }

            if (!$error) {
                return 1;
            } else {
                return -1;
            }


			/*if($_REQUEST['actionATM'] == 'createVersion') {
				setEventMessage('Version sauvegardée avec succès.', 'mesgs');
			}*/

            /* TODO J'ai essayé de rajouter un exit ici, ce qui serait complètement logique, mais ça a tout cassé...
             * Visiblement, le module est conçu pour que le script continue de s'exécuter. Dont acte, mais entre ça, les
             * redirections en JS plutôt que via header(), et les messages de retour utilisateur passés en paramètre
             * lors de la redirection, on est dans une méthodologie bien dégueulasse, il y a donc du refaisage à
             * entreprendre à mon sens - MdLL, 07/04/2020
             */
		}

		static function archivePDF(&$object)
		{
			global $db;

            $versionNum = self::getVersionNumFromProposalOrVersionList($db, $object);

			if ($object->entity > 1) {
				$filename = DOL_DATA_ROOT . '/' . $object->entity . '/propale/' . $object->ref . '/' .$object->ref;
				$path = DOL_DATA_ROOT . '/' . $object->entity . '/propale/' . $object->ref . '/' .$object->ref . '.pdf';
			}
			else {
				$filename = DOL_DATA_ROOT . '/propale/' . $object->ref . '/' .$object->ref;
				$path = DOL_DATA_ROOT . '/propale/' . $object->ref . '/' .$object->ref . '.pdf';
			}

            $ok = self::generatePDF($object);

			if ($ok > 0)
			{
//				exec('cp "'.$path.'" "'.$filename.'-'.$versionNum.'.pdf"');
				copy($path, $filename.'-'.$versionNum.'.pdf');
			}
		}

		static function generatePDF(&$object)
		{
			global $conf, $langs;

            // Define output language
            $outputlangs = $langs;
            if (!empty($conf->global->MAIN_MULTILANGS)) {
                $outputlangs = new Translate('', $conf);
                $newlang = (GETPOST('lang_id', 'aZ09') ? GETPOST('lang_id', 'aZ09') : $object->thirdparty->default_lang);
                $outputlangs->setDefaultLang($newlang);
            }

            // PDF
            $hidedetails = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DETAILS) ? 1 : 0);
            $hidedesc = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_DESC) ? 1 : 0);
            $hideref = (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_HIDE_REF) ? 1 : 0);

            return $object->generateDocument($object->modelpdf, $outputlangs, $hidedetails, $hidedesc, $hideref);
		}

        /**
         * Restore a proposal
         *
         * @param   PDO     $PDOdb                  Database connection
         * @param   Propal  $object                 Proposal object
         * @param   int     $versionNum             [=0] To restore on new version or version number to restore on
         * @return  void
         */
		static function restaurerPropale(&$PDOdb, &$object, $versionNum = 0) {

			global $db, $user,$langs;

			$versionPropale = new TPropaleHist;
			$versionPropale->load($PDOdb, $_REQUEST['idVersion']);

			$propale = $versionPropale->getObject();

			$propale->statut = 0;
			$object->statut = 0;

            // keep parent lines from proposal to restore
            $proposalLineNumFromIdList = array();
            $proposalParentLineNumList = array();
            foreach($propale->lines as $lineNum => $line) {
                $proposalLineNumFromIdList[$line->id] = $lineNum;

                $parentLineId = $line->fk_parent_line;
                if ($parentLineId > 0 && isset($proposalLineNumFromIdList[$parentLineId])) {
                    $proposalParentLineNumList[$lineNum] = $proposalLineNumFromIdList[$parentLineId];
                }
            }

			foreach($object->lines as $line) {
				$object->deleteline($line->rowid);
			}

            $newProposalLineIdList = array();
            foreach($propale->lines as $lineNum => $line) {
                // get new parent line id from parent line num
                $parentLineNum = isset($proposalParentLineNumList[$lineNum]) ? $proposalParentLineNumList[$lineNum] : -1;
                $newProposalLineParentId = $line->fk_parent_line;
                if (isset($newProposalLineIdList[$parentLineNum])) {
                    $newProposalLineParentId = $newProposalLineIdList[$parentLineNum];
                }

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
                    $newProposalLineParentId,
					$line->fk_fournprice,
					$line->pa_ht,
					$line->label,
					$line->date_start,
					$line->date_end,
					$line->array_options
				);

                $newProposalLineIdList[$lineNum] = $object->line->id;
			}

            // set extra fields before update
            $object->array_options = $propale->array_options;
            $object->array_options['options_propalehistory_version_num'] = (empty($versionNum) ? null : $versionNum);

			if (method_exists($object, 'set_draft')) $object->set_draft($user); // Pour pouvoir modifier les dates, le statut doit être à 0
			else $object->setDraft($user);

			$object->set_availability($user, $propale->availability_id);
			$object->set_date($user, $propale->date);
			if (is_callable(array($object, 'setDeliveryDate'))) {
				$object->setDeliveryDate($user, $propale->date_livraison);
			} else {
				$object->set_date_livraison($user, $propale->date_livraison);
			}
			$object->set_echeance($user, $propale->fin_validite);
			$object->set_ref_client($user, $propale->ref_client);
			$object->set_demand_reason($user, $propale->demand_reason_id);
			$object->setPaymentMethods($propale->mode_reglement_id);
			$object->setPaymentTerms($propale->cond_reglement_id);
            // update public note
            $object->update_note($propale->note_public, '_public');
            // update extra fields
            $object->insertExtraFields();
			$object->valid($user,1);
			$object->fetch($object->id); //reload for generatePDF
			self::generatePDF($object);

			header('Location: '.$_SERVER['PHP_SELF'].'?id='.$_REQUEST['id'].'&mesg='.$langs->transnoentities('HistoryVersionSuccessfullRestored'));

            /* TODO J'ai essayé de rajouter un exit ici, ce qui serait complètement logique, mais ça a tout cassé...
             * Visiblement, le module est conçu pour que le script continue de s'exécuter. Dont acte, mais entre ça, les
             * redirections en JS plutôt que via header(), et les messages de retour utilisateur passés en paramètre
             * lors de la redirection, on est dans une méthodologie bien dégueulasse, il y a donc du refaisage à
             * entreprendre à mon sens - MdLL, 07/04/2020
             */
		}

        /**
         * Get version number from proposal
         *
         * @param   DoliDB      $db             Database connection
         * @param   Propal      $proposal       Proposal object
         * @param   array       $versionList    [=array()] Version list
         * @return  int
         */
        public static function getVersionNumFromProposalOrVersionList(&$db, $proposal, $versionList = array())
        {
            global $conf;

            $fromVersionList = false;

            if (!empty($conf->global->PROPALEHISTORY_RESTORE_KEEP_VERSION_NUM)) {
                if (!empty($proposal->array_options['options_propalehistory_version_num'])) {
                    $versionNum = $proposal->array_options['options_propalehistory_version_num'];
                } else {
                    $fromVersionList = true;
                }
            } else {
                $fromVersionList = true;
            }

            if ($fromVersionList === true) {
                if (empty($versionList)) {
                    $versionList = self::getVersions($db, $proposal->id);
                }
                $versionNumLast = self::getVersionNumLastFromVersionList($versionList);
                $versionNum = $versionNumLast + 1;
            }

            return $versionNum;
        }

        /**
         * Get next version number
         *
         * @param   DoliDB  $db             Database connection
         * @param   int     $fk_object      Proposal id
         * @return  int     Next version number
         */
        protected static function getVersionNumNext(&$db, $fk_object) {
            $TVersion = self::getVersions($db, $fk_object);
            $versionNumLast = self::getVersionNumLastFromVersionList($TVersion);
            return $versionNumLast + 1;
        }

        /**
         * Get last version number
         *
         * @param   array       $versionList    [=array()] Version list
         * @return  int         Last version number
         */
        protected static function getVersionNumLastFromVersionList($versionList = array())
        {
            global $conf;

            $versionNumLast = 0;

            if (!empty($versionList)) {
                $versionNumLast = count($versionList);

                if (!empty($conf->global->PROPALEHISTORY_RESTORE_KEEP_VERSION_NUM)) {
                    $versionPropale = new TPropaleHist;

                    foreach ($versionList as $row) {
                        $versionPropale->serialized_parent_propale = $row->serialized_parent_propale;
                        $propale = $versionPropale->getObject();
                        if (!empty($propale->array_options['options_propalehistory_version_num'])) {
                            if ($propale->array_options['options_propalehistory_version_num'] > $versionNumLast) {
                                $versionNumLast = $propale->array_options['options_propalehistory_version_num'];
                            }
                        }
                    }
                }
            }

            return $versionNumLast;
        }

		static function getVersions(&$db, $fk_object) {

			$sql = "";
			$sql.= " SELECT rowid, date_version, date_cre, total, serialized_parent_propale";
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

        /**
         * Show proposals versions and get current version number
         *
         * @param   DoliDb      $db         Database connection
         * @param   Propal      $object     Proposal object
         * @return  int
         */
		static function listeVersions(&$db, $object) {
			global $langs,$conf,$hookmanager;

			$TVersion = self::getVersions($db, $object->id);

			$newToken = function_exists('newToken') ? newToken() : $_SESSION['newtoken'];

			$num = count($TVersion);
            $versionNumCurrent = self::getVersionNumFromProposalOrVersionList($db, $object, $TVersion);

			$url=DOL_URL_ROOT.'/comm/propal.php';
			if ((float) DOL_VERSION >= 4.0) {
			    $url=DOL_URL_ROOT.'/comm/propal/card.php';
			}

			if($num>0) {

				print '<div id="formListe" style="clear:both; margin:15px 0">';
				print '<form name="formVoirPropale" method="POST" action="'.$url.'?id='.GETPOST('id','int').'">';
				print '<input type="hidden" name="actionATM" value="viewVersion" />';
				print '<input type="hidden" name="socid" value="'.$object->socid.'" />';

				if(function_exists('newToken')){
					print '<input type="hidden" name="token" value="'. $newToken .'" />';
				}

				print '<select id="propalehistory_id" name="idVersion">';
				$i = 1;
                $versionNumSelected = $i;

				foreach ($TVersion as &$row) {
                    $versionNumRow = $i;
                    if (!empty($conf->global->PROPALEHISTORY_RESTORE_KEEP_VERSION_NUM)) {
                        $versionPropale = new TPropaleHist;
                        $versionPropale->serialized_parent_propale = $row->serialized_parent_propale;
                        $propale = $versionPropale->getObject();
                        if (!empty($propale->array_options['options_propalehistory_version_num'])) {
                            $versionNumRow = $propale->array_options['options_propalehistory_version_num'];
                        }
                    }

					if(isset($_REQUEST['idVersion']) && $_REQUEST['idVersion'] == $row->rowid){
						$selected = 'selected="selected"';
                        $versionNumCurrent = $versionNumRow;
                        $versionNumSelected = $versionNumRow;
					} else {
						$selected = "";
					}

					$options = '<option id="' . $row->rowid . '" value="' . $row->rowid . '" ' . $selected . ' data-version-num="' . $versionNumRow . '">'.$langs->trans('VersionNumberShort').' ' . $versionNumRow . ' - ' . price($row->total) . ' ' . $langs->getCurrencySymbol($conf->currency, 0) . ' - ' . dol_print_date($db->jdate($row->date_cre), "dayhour") . '</option>';
					$hookmanager->initHooks(array('propalehistory'));
					$parameters = array('row' => $row, 'selected' => $selected, 'versionNumber' => $versionNumRow);
					$action = '';
					$reshook = $hookmanager->executeHooks('listeVersion_customOptions', $parameters, $object, $action);    // Note that $action and $object may have been modified by some hooks
					if ($reshook > 0) $options = $hookmanager->resPrint;

					print $options;
					$i++;

				}

				print '</select>';

                print '<input type="hidden" id="propalehistory_version_num_selected" name="propalehistory_version_num_selected" value="' . $versionNumSelected . '" />';

				print '<input class="butAction" id="voir" value="'.$langs->trans('Visualiser').'" type="SUBMIT" />';
				print '</form>';
				print '</div>';

				?>
				<script type="text/javascript">
					$(document).ready(function(){
						$("#formListe").appendTo('div.tabsAction');

                        $("#propalehistory_id").change(function() {
                            var optionSelectedElem = $("option:selected", this);
                            $("#propalehistory_version_num_selected").val(optionSelectedElem.attr("data-version-num"));
                        });
					})
				</script>
				<?php
			}

			return $versionNumCurrent;
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

			function getLinesArray($filters = '')
    		{
    			null;
			}

	}
