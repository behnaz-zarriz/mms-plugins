<?php
/* ----------------------------------------------------------------------
 * MMSInsuranceFeatures.php : Enthält statische Funktionen zur
 * automatischen Generierung von Versicherungswerten für Leihgaben
 * ----------------------------------------------------------------------
 * Copyright 2014 Landeshauptstadt München
 * @version 0.1
 * ----------------------------------------------------------------------
 */

class MMSInsuranceFeatures
{

	private static $oldInsuranceVals = [];

	/**
	 * Speichert den aktuellen Versicherungswert vor dem Speichern
	 *
	 * @param $pa_params
	 * @return void
	 */
	public static function rememberOldInsuranceValue(&$pa_params)
	{
		$po_object = $pa_params['instance'];

		if ($po_object->tableName() !== 'ca_objects') {
			return;
		}

		$id = $po_object->getPrimaryKey();

		// Vorherigen Wert als Array speichern
		self::$oldInsuranceVals[$id] = $po_object->get('insurance_value_current', ['returnAsArray' => true]);
	}

	/**
	 * Wird nach dem Speichern aufgerufen, prüft Änderungen und speichert alten Wert historisch
	 *
	 * @param $pa_params
	 * @return void
	 */
	public static function handleInsuranceValueHistoryOnUpdate(&$pa_params)
	{
		$po_object = $pa_params['instance'];

		if ($po_object->tableName() !== 'ca_objects') {
			return;
		}

		$id = $po_object->getPrimaryKey();
		$oldVals = self::$oldInsuranceVals[$id] ?? null;
		$newVals = $po_object->get('insurance_value_current', ['returnAsArray' => true]);


		// Keine Änderung oder keine alten/neuen Werte → kein Handlungsbedarf
		if (!$oldVals || !$newVals || json_encode($oldVals) === json_encode($newVals)) {
			unset(self::$oldInsuranceVals[$id]);
			return;
		}

		$historicValues = $po_object->get('insurance_value_historic', ['returnAsArray' => true]);
		// Prüft, ob 'insurance_value_historic' nur leere Platzhalter enthält (z. B. ';;') und entfernt es in diesem Fall
		$filtered = array_filter($historicValues, function ($val) {
			return trim($val) !== '' && trim($val) !== ';;';
		});
		if (empty($filtered)) {
			$po_object->removeAttributes('insurance_value_historic');
		}

		foreach ($oldVals as $oldVal) {
			$parts = array_map('trim', explode(';', $oldVal));

			$date_raw = $parts[0] ?? date('Y-m-d');
			$date_str = date('Y-m-d', strtotime($date_raw));  // gültiges Datum erzeugen

			$value_raw = $parts[1] ?? '';
			$value_float = mmsExtractFloatFromCurrencyValue($value_raw);
			$value_currency = mmsFloatToCurrencyValue($value_float);

			$remark = $parts[2] ?? '';

			$historicData = ['historic_date' => $date_str, 'historic_value_eur' => $value_currency, 'historic_remark' => $remark,];

			$po_object->addAttribute($historicData, 'insurance_value_historic');
		}

		// Änderungen speichern
		$po_object->update();
		unset(self::$oldInsuranceVals[$id]);
	}


	/**
	 * Berechnet bei Änderung eines Objektes oder einer Leihgabe die
	 * Gesamt-Versicherungssumme für alle betroffenen Leihgaben neu
	 *
	 * @param array $pa_params Parameter-Array, das vom Plugin Hook übergeben wird
	 */
	public static function calcLoanInsuranceVal(&$pa_params)
	{

		switch ($pa_params['instance']->tableName()) {
			// Leihgabe ist neu oder hat sich geändert
			case 'ca_loans':
				// berechne Versicherungswert neu
				self::setInsuranceValForLoan($pa_params['instance']);
				break;
			// Ein Objekt ist neu oder hat sich geändert
			case 'ca_objects':
				// Hole alle angehängten Leihgaben und berechne Versicherungswerte neu
				$va_loans = $pa_params['instance']->getRelatedItems('ca_loans');
				$t_loan = new ca_loans();
				foreach ($va_loans as $vn_loan_id => $va_loan_info) {
					if ($t_loan->load($vn_loan_id)) {
						self::setInsuranceValForLoan($t_loan);
					}
				}
				break;
			default:
				return;
		}
	}

	/**
	 * Hilfsfunktion, die für eine gegebene Leihgabe die Versicherungssumme berechnet und setzt
	 *
	 * @param ca_loans $t_loan BaseModel Instanz der betroffenen Leihgabe
	 */
	private static function setInsuranceValForLoan(&$t_loan)
	{
		// Hole alle Objekte
		$va_objects = $t_loan->getRelatedItems('ca_objects');
		$t_object = new ca_objects();
		$vn_insurance_sum = 0;
		foreach ($va_objects as $vn_index => $va_object_info) {
			// Wie wird summiert? Jedes Objekt kann mehrere Werte haben
			// Wir nehmen nur den neuesten her. Dieser lässt sich einfach finden, indem man nach PK sortiert
			$t_object->load($va_object_info['object_id']);
			$va_values = $t_object->get('ca_objects.insurance_value_current.current_value_eur', ['returnAsArray' => true]);
			if (!is_array($va_values)) {
				continue;
			}

			ksort($va_values); // sortiere nach Primärschlüssel der Werte
			$vs_val = array_pop($va_values); // wir nehmen den neuesten Wert

			$vn_insurance_sum += mmsExtractFloatFromCurrencyValue($vs_val);
		}

		// editiere den existierenden automatisch gesetzten Wert oder lege einen neuen an
		$t_loan->replaceAttribute(array('loan_insurance_remark' => mmsGetSettingFromMMSPluginConfig('lhm_mms_loan_insurance_comment'), 'loan_insurance_value_eur' => mmsFloatToCurrencyValue($vn_insurance_sum),), 'loan_insurance');

		$t_loan->update();
	}


}
