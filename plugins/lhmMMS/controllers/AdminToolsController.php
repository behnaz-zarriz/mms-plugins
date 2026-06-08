<?php
/* ----------------------------------------------------------------------
 * plugins/lhmMMS/controllers/AdminToolsController.php
 * ----------------------------------------------------------------------
 * Copyright 2016 Landeshauptstadt München
 * ----------------------------------------------------------------------
 */

require_once(__CA_BASE_DIR__.'/lhm/lib/SanityCheck.php');
require_once(__CA_MODELS_DIR__ . '/ca_sets.php');


class AdminToolsController extends ActionController {
	# -------------------------------------------------------
	public function __construct(&$po_request, &$po_response, $pa_view_paths=null) {
		parent::__construct($po_request, $po_response, $pa_view_paths);

		if(!$this->getRequest()->getUser()->canDoAction('can_use_mms_admin_tools')) {
			throw new Exception('Sie haben nicht die nötigen Berechtigungen für Zugang zu diesem Menüpunkt.');
		}

		AssetLoadManager::register('tableList');
	}
	# -------------------------------------------------------
	public function DupeIdnoCheck() {
		AssetLoadManager::register('tableList');
		$o_db = new Db();
		
		$ps_page = $this->request->getParameter('p', pString);
		
		$qr_dupe_idnos = $o_db->query('SELECT idno FROM ca_objects WHERE deleted = 0 GROUP BY idno HAVING count(*) > 1');
		
		$va_dupe_map = $va_pages = [];
		
		if (is_array($va_idnos = $qr_dupe_idnos->getAllFieldValues('idno')) && (sizeof($va_idnos) > 0)) { 
			
			$va_pages = array_keys(array_flip(array_map(function($v) { return strtoupper(substr($v, 0, 1)); }, $va_idnos)));
			sort($va_pages);
		
			if (!$ps_page) {$ps_page = $va_pages[0]; }
		
			$qr_objects = $o_db->query('SELECT object_id, idno FROM ca_objects WHERE deleted = 0 AND idno '.(strlen($ps_page) ? "LIKE" : " = ").' ? AND idno IN (?) ORDER BY idno_sort', [strlen($ps_page) ? "{$ps_page}%" : "", $va_idnos]);

			$va_object_ids = $qr_objects->getAllFieldValues('object_id');
		
			$va_processed_templates = caProcessTemplateForIDs("<l>^ca_objects.preferred_labels (^ca_objects.object_id)</l>", 'ca_objects', $va_object_ids, ['returnAsArray' => true]);

			$qr_objects->seek(0);
		
			$vn_i = 0;
			while($qr_objects->nextRow()) {
				$va_dupe_map[$qr_objects->get('idno')][] = $va_processed_templates[$vn_i];
				$vn_i++;
			}
		}

		$this->getView()->setVar('pages', $va_pages);
		$this->getView()->setVar('page', $ps_page);
		$this->getView()->setVar('dupe_map', $va_dupe_map);
		
		$this->render('dupe_idno_check_html.php');
	}
	# -------------------------------------------------------
	public function FieldValues() {
		$va_elements = ca_metadata_elements::getElementsAsList(false, null, null, true, true);


		$va_elements_for_list = [];
		foreach($va_elements as $vn_k => $va_v) {
			// no containers, file or media attributes
			if(in_array((int)$va_v['datatype'], [__CA_ATTRIBUTE_VALUE_CONTAINER__, __CA_ATTRIBUTE_VALUE_FILE__, __CA_ATTRIBUTE_VALUE_MEDIA__])) {
				continue;
			}

			$t_element = new ca_metadata_elements($vn_k);
			while($t_element->get('parent_id')) {
				$t_element->load($t_element->get('parent_id'));
			}

			foreach($t_element->getTypeRestrictions() as $va_restriction) {

				$va_elements_for_list[$vn_k.$va_restriction['table_num']] = $va_v;
				$va_elements_for_list[$vn_k.$va_restriction['table_num']]['table_num'] = $va_restriction['table_num'];
			}
		}

		$this->getView()->setVar('element_list', $va_elements_for_list);
		if (__CollectiveAccess__ < '2.0') {
				$this->getView()->setVar('attribute_types', Attribute::getAttributeTypes());
		} else {
				$this->getView()->setVar('attribute_types', CA\Attributes\Attribute::getAttributeTypes());
		}
		
		$this->render('elements_list_html.php');
	}
	# -------------------------------------------------------
	public function FieldValsForElement() {
		$pn_element_id = $this->getRequest()->getParameter('element_id', pInteger);
		$pn_table_num = $this->getRequest()->getParameter('table_num', pInteger);
		if(!$pn_element_id) { return false; }
		if(!$pn_table_num) { return false; }

		$this->getView()->setVar('table_num', $pn_table_num);

		// generate report for this element (@todo move this to model?)
		$o_db = new Db();
		
		//$o_dm = Datamodel::load();
		if (!($t_rel = Datamodel::getInstanceByTableNum($pn_table_num, true))) { throw new Exception("Invalid table number: %1", $pn_table_num); }
		$vs_rel_table_name = $t_rel->tableName();
		$vs_rel_pk = $t_rel->primaryKey();
		
		$vs_sql_deleted = ($t_rel->hasField('deleted')) ? " AND t.deleted = 0" : "";
		
		$qr_vals = $o_db->query($x="
				SELECT * FROM ca_attribute_values cav
				INNER JOIN ca_attributes AS a ON a.attribute_id = cav.attribute_id
				INNER JOIN {$vs_rel_table_name} AS t ON t.{$vs_rel_pk} = a.row_id
				WHERE cav.element_id = ?
				AND a.table_num = ? {$vs_sql_deleted}
			", $pn_element_id, $pn_table_num);

		$t_element = new ca_metadata_elements($pn_element_id);
		$this->getView()->setVar('t_element', $t_element);

		$va_value_counts = [];
		$va_value_records = [];
		while($qr_vals->nextRow()) {
			$va_row = $qr_vals->getRow();

			switch($t_element->get('datatype')) {
				case __CA_ATTRIBUTE_VALUE_DATERANGE__:
					$o_val = new DateRangeAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_LIST__:
					$o_val = new ListAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue(['list_id' => ca_metadata_elements::getElementListID($va_row['element_id'])]);
					break;
				case __CA_ATTRIBUTE_VALUE_CURRENCY__:
					$o_val = new CurrencyAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_LENGTH__:
					$o_val = new LengthAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_NUMERIC__:
					$o_val = new NumericAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_GEONAMES__:
					$o_val = new GeoNamesAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_TIMECODE__:
					$o_val = new TimeCodeAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_INTEGER__:
					$o_val = new IntegerAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_GEOCODE__:
					$o_val = new GeocodeAttributeValue($va_row);
					$vs_value = $o_val->getDisplayValue();
					break;
				case __CA_ATTRIBUTE_VALUE_TEXT__:
				default:
					$vs_value = $va_row['value_longtext1'];
			}
            $vs_value = trim($vs_value);
			$va_value_counts[$vs_value]++;
			$va_value_records[$vs_value][] = $va_row['value_id'];
		}

		arsort($va_value_counts);

		$this->getView()->setVar('value_records', $va_value_records);
		$this->getView()->setVar('value_counts', $va_value_counts);

		$this->render('vals_for_element_html.php');
	}
	# -------------------------------------------------------
	public function CreateSetForValue() {
		$pn_value_id = $this->getRequest()->getParameter('value_id', pInteger);
		$pn_table_num = $this->getRequest()->getParameter('table_num', pInteger);
		$pn_element_id = $this->getRequest()->getParameter('element_id', pInteger);

		$pb_batch = (bool) $this->getRequest()->getParameter('batch', pInteger);

		$o_db = new Db();

		$qr_val = $o_db->query('SELECT * FROM ca_attribute_values WHERE value_id=?', $pn_value_id);
		if($qr_val->nextRow()) {
			$va_row = $qr_val->getRow();

			$va_wheres = ['ca_attributes.table_num = ?', 'cav.element_id = ?'];
			$va_params = [$pn_table_num, $pn_element_id];

			if(isset($va_row['value_longtext1']) && $va_row['value_longtext1']) {
				$va_wheres[] = "cav.value_longtext1 = ?";
				$va_params[] = $va_row['value_longtext1'];
			}

			if(isset($va_row['value_longtext2']) && $va_row['value_longtext2']) {
				$va_wheres[] = "cav.value_longtext2 = ?";
				$va_params[] = $va_row['value_longtext2'];
			}

			if(isset($va_row['value_decimal1']) && $va_row['value_decimal1']) {
				$va_wheres[] = "cav.value_decimal1 = ?";
				$va_params[] = $va_row['value_decimal1'];
			}

			if(isset($va_row['value_decimal2']) && $va_row['value_decimal2']) {
				$va_wheres[] = "cav.value_decimal2 = ?";
				$va_params[] = $va_row['value_decimal2'];
			}

			if(isset($va_row['value_integer1']) && $va_row['value_integer1']) {
				$va_wheres[] = "cav.value_integer1 = ?";
				$va_params[] = $va_row['value_integer1'];
			}

			$qr_all_vals = $o_db->query('
					SELECT * FROM ca_attributes
					INNER JOIN ca_attribute_values cav ON ca_attributes.attribute_id = cav.attribute_id
					WHERE '.join(' AND ', $va_wheres), $va_params);

			$t_set = new ca_sets();
			$t_set->setMode(ACCESS_WRITE);
			$t_set->set('set_code', $vs_code = 'mms_admin_tools_' . time());
			$t_set->set('table_num', $pn_table_num);
			$t_set->set('type_id', 'user');
			$t_set->set('user_id', $this->getRequest()->getUserID());
			$t_set->insert();

			$t_set->addLabel([
				'name' => $vs_code
			], 1, null, true);

			$pa_ids = $qr_all_vals->getAllFieldValues('row_id');
			$t_set->addItems($pa_ids, ['queueIndexing' => false]);

			if($pb_batch) { // redisrect to batch edit action for the new set
				$this->getResponse()->setRedirect(caNavUrl($this->getRequest(), 'batch', 'Editor', 'Edit', array('set_id' => $t_set->getPrimaryKey())));
			} else { // search for set
				$pa_additional_params = [];
				if((int)$pn_table_num == 67) { // take a wild guess at the occurrence type
					$t_occ = new ca_occurrences(array_shift($pa_ids));
					$pa_additional_params['type_id'] = $t_occ->getTypeID();
				}

				$this->getResponse()->setRedirect(caSearchUrl($this->getRequest(), $pn_table_num, 'set:"'.$vs_code.'"', false, $pa_additional_params));
			}
		}
	}
	# -------------------------------------------------------
	public function OrphanedMedia() {
		AssetLoadManager::register('tableList');

		$o_db = new Db();

		// $qr_media = $o_db->query('SELECT representation_id FROM ca_object_representations WHERE deleted=0 ORDER BY representation_id ASC');
// 		$va_media_list = [];
// 
// 		if($o_res = caMakeSearchResult('ca_object_representations', $qr_media->getAllFieldValues('representation_id'))) {
// 			while($o_res->nextHit()) {
// 				foreach([
// 							'ca_objects.object_id',
// 							'ca_object_lots.lot_id', 'ca_entities.entity_id',
// 							'ca_collections.collection_id', 'ca_occurrences.occurrence_id',
// 							'ca_storage_locations.location_id', 'ca_loans.loan_id'
// 						] as $vs_key) {
// 					if(sizeof($o_res->get($vs_key, ['returnAsArray' => true]))) {
// 						continue 2; // continue if it's not orphaned
// 					}
// 				}
// 
// 				// if we reach this point, it's orphaned, so add to list
// 				$va_media_list[$o_res->get('ca_object_representations.representation_id')] = [
// 					'thumbnail' => $o_res->get('ca_object_representations.media.thumbnail'),
// 					'representation_id' => $o_res->get('ca_object_representations.representation_id'),
// 					'original_filename' => $o_res->get('ca_object_representations.original_filename')
// 				];
// 			}
// 		}

		$rel_tables = [
			'ca_objects_x_object_representations', 'ca_object_representations_x_entities',
			'ca_object_lots_x_object_representations', 'ca_object_representations_x_collections',
			'ca_object_representations_x_occurrences', 'ca_object_representations_x_storage_locations',
			'ca_loans_x_object_representations'
		];
		
		$orphans_by_table = [];
		$acc = null;
		foreach($rel_tables as $rt) {
			$in_sql = null; $params = [];
			
			if($acc) { 
				$in_sql = ' representation_id IN (?) AND ';
				$params[] = $acc;
			}
			$qr = $o_db->query($z="SELECT representation_id FROM ca_object_representations WHERE {$in_sql} representation_id NOT IN (SELECT representation_id FROM {$rt}) AND deleted = 0;", $params);

			if($qr && ($qr->numRows() > 0)) {
				$orphans = $qr->getAllFieldValues('representation_id');
				if(!$acc) { 
					$acc = $orphans; 
				} else {
					$acc = array_intersect($acc, $orphans);
				}
			}
		}
		
		$va_media_list = [];
		if (sizeof($acc) && ($o_res = caMakeSearchResult('ca_object_representations', $acc))) {
			while($o_res->nextHit()) {
				$va_media_list[$o_res->get('ca_object_representations.representation_id')] = [
					'thumbnail' => $o_res->get('ca_object_representations.media.thumbnail'),
					'representation_id' => $o_res->get('ca_object_representations.representation_id'),
					'original_filename' => $o_res->get('ca_object_representations.original_filename')
				];
			}
		}
		$this->getView()->setVar('orphaned_media_list', $va_media_list);

		$this->render('orphaned_media_list_html.php');
	}
	# -------------------------------------------------------
	public function DataStats() {
		if(!($ps_date_range = $this->getRequest()->getParameter('data_stats_search', pString))) {
			$ps_date_range = 'heute';
		}

		// KULTMMS-737: Disabling absolute stats in Front-End
		//$this->getView()->setVar('absolute_stats', self::getAbsoluteStats());

		$o_tep = new TimeExpressionParser(null, 'de_DE');
		if(!$o_tep->parse($ps_date_range)) {
			$ps_date_range = 'heute';
			$o_tep->parse($ps_date_range);
		}

		$this->getView()->setVar('data_stats_search', $ps_date_range);
		$va_t = $o_tep->getUnixTimestamps();
		$this->getView()->setVar('date_range_for_display', date('d.m.Y H:i', $va_t['start']) . ' - ' . date('d.m.Y H:i', $va_t['end']));

		$this->getView()->setVar('relative_stats', self::getRelativeStats($va_t));

		$this->render('data_stats_html.php');
	}
	# -------------------------------------------------------
	private static function getDirSize($dir) {
		return caDirectorySize($dir);
	}
	# -------------------------------------------------------
	/**
	 * Get absolute stats
	 * @return array
	 */
	public static function getAbsoluteStats() {
		$o_db = new Db();

		$va_absolute_stats = [];

		$va_stats_queries = [
			'Objekte' => 'SELECT count(*) as c FROM ca_objects WHERE deleted=0',
			'Zugänge' => 'SELECT count(*) as c FROM ca_object_lots WHERE deleted=0',
			'Ausstellungen/Objektgruppen/Konvolute' => 'SELECT count(*) as c FROM ca_occurrences WHERE deleted=0',
			'Personen/Institutionen/Ethnien' => 'SELECT count(*) as c FROM ca_entities WHERE deleted=0',
			'Standorte' => 'SELECT count(*) as c FROM ca_storage_locations WHERE deleted=0',
			'Leihgaben' => 'SELECT count(*) as c FROM ca_loans WHERE deleted=0',
			'Sets' => 'SELECT count(*) as c FROM ca_sets WHERE deleted=0',
			'Medien' => 'SELECT count(*) as c FROM ca_object_representations WHERE deleted=0',
		];

		foreach($va_stats_queries as $vs_name => $vs_q) {
			$qr_records = $o_db->query($vs_q);
			$qr_records->nextRow();
			$va_absolute_stats[$vs_name] = $qr_records->get('c');
		}

		// Medien-Speicherplatz
		$va_absolute_stats['Speicherplatzbelegung durch Medien und Anhänge'] =
			caHumanFilesize(
				self::getDirSize(__MMS_INSTANCE_MEDIA_ROOT_DIR__) +
				self::getDirSize(__MMS_INSTANCE_ARCHIVE_ROOT_DIR__)
			);

		return $va_absolute_stats;
	}
	# -------------------------------------------------------
	public static function getRelativeStats($pa_timestamps) {
		if(!is_array($pa_timestamps) || !isset($pa_timestamps['start']) || !isset($pa_timestamps['end'])) {
			return [];
		}

		$o_db = new Db();
		//$o_dm = Datamodel::load();

		$va_relative_stats_queries = [
			'Objekte' => (int)Datamodel::getTableNum('ca_objects'),
			'Zugänge' => (int)Datamodel::getTableNum('ca_object_lots'),
			'Ausstellungen/Objektgruppen/Konvolute' => (int)Datamodel::getTableNum('ca_occurrences'),
			'Personen/Institutionen/Ethnien' => (int)Datamodel::getTableNum('ca_entities'),
			'Standorte' => (int)Datamodel::getTableNum('ca_storage_locations'),
			'Leihgaben' => (int)Datamodel::getTableNum('ca_loans'),
			'Sets' => (int)Datamodel::getTableNum('ca_sets'),
			'Medien' => (int)Datamodel::getTableNum('ca_object_representations'),
		];

		$va_relative_stats = [];
		foreach($va_relative_stats_queries as $vs_name => $vn_table_num) {
			$qr_records = $o_db->query('SELECT count(*) AS c FROM ca_change_log WHERE logged_table_num=? AND changetype=? AND (log_datetime BETWEEN ? AND ?)', $vn_table_num, 'I', $pa_timestamps['start'], $pa_timestamps['end']);
			$qr_records->nextRow();
			$va_relative_stats[$vs_name] = $qr_records->get('c');
		}

		// Eingefügte Medien suchen
		$qr_media = $o_db->query("
				SELECT logged_row_id FROM ca_change_log
				WHERE logged_table_num=56 AND changetype=? AND (log_datetime BETWEEN ? AND ?)
			", 'I', $pa_timestamps['start'], $pa_timestamps['end']);

		$o_res = caMakeSearchResult('ca_object_representations', $qr_media->getAllFieldValues('logged_row_id'));
		$vn_bytes = 0;
		if($o_res) {
			while ($o_res->nextHit()) {
				foreach(['tilepic', 'original', 'h264_hi', 'mp3'] as $vs_version) {
					$vs_path = $o_res->getMediaPath('media', $vs_version);
					if($vs_path && file_exists($vs_path)) {
						$vn_bytes += filesize($vs_path);
					}
				}
			}
		}
		$va_relative_stats['Speicherplatzbelegung durch Medien'] = caHumanFilesize($vn_bytes);
		return $va_relative_stats;
	}
}
