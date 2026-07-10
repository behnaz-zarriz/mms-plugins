<?php
/* ----------------------------------------------------------------------
 * app/plugins/lhmMMS/views/vals_for_element_html.php
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2016 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * This source code is free and modifiable under the terms of
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * ----------------------------------------------------------------------
 */

/** @var ca_metadata_elements $t_element */
$t_element = $this->getVar('t_element');
$va_value_counts = $this->getVar('value_counts');
$va_value_records = $this->getVar('value_records');
$vn_table_num = $this->getVar('table_num');

print caNavLink($this->request, '&lt; Zurück zur Liste', '', 'lhmMMS', 'AdminTools', 'FieldValues');
?>

<h3>Analyse existierender Feldwerte für <?php print caGetTableDisplayName($vn_table_num); ?> und "<?php print $t_element->getLabelForDisplay(); ?>" [<?php print $t_element->get('element_code'); ?>]</h3>

<script language="JavaScript" type="text/javascript">
    /* <![CDATA[ */
    $(document).ready(function(){
        $('#caValueCountList').caFormatListTable();
    });
    /* ]]> */
</script>
<div class="sectionBox">
    <?php
    print caFormControlBox(
            '<div class="list-filter">'._t('Filter').': <input type="text" name="filter" value="" onkeyup="$(\'#caValueCountList\').caFilterTable(this.value); return false;" size="20"/></div>',
            '',
            ''
    );
    ?>

    <table id="caValueCountList" class="listtable" width="100%" border="0" cellpadding="0" cellspacing="1">
        <thead>
        <tr>
            <th>Wert</th>
            <th>Anzahl</th>
            <th class="{sorter: false} list-header-nosort">Neues Set</th>
        </tr>
        </thead>
        <tbody>
        <?php
        foreach($va_value_counts as $vs_val => $vn_c) {
            ?>
            <tr>
                <td>
                    <?php print $vs_val; ?>
                </td>
                <td>
                    <?php print $vn_c; ?>
                </td>
                <td>
                    <?php
                    /*print caNavLink($this->request, caNavIcon( __CA_NAV_ICON_SEARCH__, '15px'), 'mmsSetSearch', 'lhmMMS', 'AdminTools', 'CreateSetForValue', ['value_id' => array_pop($va_value_records[$vs_val]), 'table_num' => $vn_table_num, 'element_id' => $t_element->getPrimaryKey()]);
                    print "&nbsp; &nbsp;";
                    print caNavLink($this->request, caNavIcon( __CA_NAV_ICON_BATCH_EDIT__, '15px'), 'mmsSetBatch', 'lhmMMS', 'AdminTools', 'CreateSetForValue', ['value_id' => array_pop($va_value_records[$vs_val]), 'table_num' => $vn_table_num, 'element_id' => $t_element->getPrimaryKey(), 'batch' => 1]);*/
                    //$vs_value_ids = join(',', $va_value_records[$vs_val]);
                    print caNavLink(
                            $this->request,
                            caNavIcon(__CA_NAV_ICON_ADD__, '15px'),
                            'mmsSetSearch',
                            'lhmMMS',
                            'AdminTools',
                            'CreateSetForValue',
                            [
                                    'display_value' => $vs_val,
                                    'table_num' => $vn_table_num,
                                    'element_id' => $t_element->getPrimaryKey()
                            ]
                    );

                    print "&nbsp; &nbsp;";

                    print caNavLink(
                            $this->request,
                            caNavIcon(__CA_NAV_ICON_BATCH_EDIT__, '15px'),
                            'mmsSetBatch',
                            'lhmMMS',
                            'AdminTools',
                            'CreateSetForValue',
                            [
                                    'display_value' => $vs_val,
                                    'table_num' => $vn_table_num,
                                    'element_id' => $t_element->getPrimaryKey(),
                                    'batch' => 1
                            ]
                    );


                    ?>
                </td>
            </tr>
            <?php
        }

        //TooltipManager::add('.mmsSetSearch', "Erstellen und suchen");
        TooltipManager::add('.mmsSetSearch', "Erstellen");
        TooltipManager::add('.mmsSetBatch', "Erstellen und zur Stapelverarbeitung öffnen");
        ?>
        </tbody>
    </table>
</div>
<div class="editorBottomPadding"><!-- empty --></div>
