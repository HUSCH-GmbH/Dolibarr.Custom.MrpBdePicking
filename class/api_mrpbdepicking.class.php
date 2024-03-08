<?php
/* Copyright (C) 2023   Christian Humpel     <christian.humpel@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT . '/mrp/class/mo.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mrpbdepicking/class/mobde.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/mrpbdepicking/class/mopickingline.class.php';


/**
 * \file    htdocs/custom/mrpbdepicking/class/api_mrpbdepicking.class.php
 * \ingroup mrpbdepicking
 * \brief   File for API management of MrpBdePicking.
 */

/**
 * API class for MrpBdePicking
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class MrpBdePicking extends DolibarrApi
{

    /**
     * Constructor
     */
    public function __construct()
    {
        global $db, $conf;
        $this->db = $db;
    }


    /**
     * List MoBde Objects (For HUSCH extended search)
     *
     * Get a list of MoBde Objects
     *
     * @param string $sortfield Sort field
     * @param string $sortorder Sort order
     * @param int $limit Limit for list
     * @param int $page Page number
     * @param string $sqlfilters Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
     * @return  array                               Array of order objects
     *
     * @throws RestException
     */
    public function getMoBdeObjects($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
    {
        global $conf;

        if (!DolibarrApiAccess::$user->rights->mrp->read) {
            throw new RestException(401);
        }

        $obj_ret = array();
        $tmpobject = new MoBde($this->db);
        $extrafields = new ExtraFields($this->db);

        // Fetch optionals attributes and labels
        $extrafields->fetch_name_optionals_label($tmpobject->table_element);

        $socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : '';

        $restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

        // If the internal user must only see his customers, force searching by him
        $search_sale = 0;
        if ($restrictonsocid && !DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) {
            $search_sale = DolibarrApiAccess::$user->id;
        }

        // Build and execute select
        // --------------------------------------------------------------------


        $sqlForPickableMoLines = $this->CreateMoPickingLinesSqlStatement();

        // Definition of array of fields for columns
        $selects = $this->getArrOfSqlSelects($tmpobject);

        $sql = "SELECT t.rowid,";
        $sql .= implode(',', $selects);
        if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
            $sql .= ", sc.fk_soc, sc.fk_user"; // We need these fields in order to filter by sale (including the case where the user can only see his prospects)
        }
        $sql .= " FROM " . MAIN_DB_PREFIX . $tmpobject->table_element . " AS t LEFT JOIN " . MAIN_DB_PREFIX . $tmpobject->table_element . "_extrafields AS ef ON (ef.fk_object = t.rowid)"; // Modification VMR Global Solutions to include extrafields as search parameters in the API GET call, so we will be able to filter on extrafields
        $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON t.fk_product = p.rowid";

        //Integration status_todo
        $sql .= " LEFT JOIN (SELECT X.rowid, 1 as status_todo FROM (";
        $sql .= " " . $sqlForPickableMoLines;
        $sql .= " ) X GROUP BY X.rowid) xl ON xl.rowid = t.rowid";

        if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
            $sql .= ", " . MAIN_DB_PREFIX . "societe_commerciaux as sc"; // We need this table joined to the select in order to filter by sale
        }
        $sql .= " WHERE 1 = 1";

        // Example of use $mode
        //if ($mode == 1) $sql.= " AND s.client IN (1, 3)";
        //if ($mode == 2) $sql.= " AND s.client IN (2, 3)";

        if ($tmpobject->ismultientitymanaged) {
            $sql .= ' AND t.entity IN (' . getEntity($tmpobject->element) . ')';
        }
        if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
            $sql .= " AND t.fk_soc = sc.fk_soc";
        }
        if ($restrictonsocid && $socid) {
            $sql .= " AND t.fk_soc = " . ((int)$socid);
        }
        if ($restrictonsocid && $search_sale > 0) {
            $sql .= " AND t.rowid = sc.fk_soc"; // Join for the needed table to filter by sale
        }
        // Insert sale filter
        if ($restrictonsocid && $search_sale > 0) {
            $sql .= " AND sc.fk_user = " . ((int)$search_sale);
        }
        if ($sqlfilters) {
            $errormessage = '';
            $sql .= forgeSQLFromUniversalSearchCriteria($sqlfilters, $errormessage);
            if ($errormessage) {
                throw new RestException(400, 'Error when validating parameter sqlfilters -> ' . $errormessage);
            }
        }

        $sql .= $this->db->order($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql .= $this->db->plimit($limit + 1, $offset);
        }

        $result = $this->db->query($sql);
        if ($result) {
            $num = $this->db->num_rows($result);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($result);
                $tmp_object = new MoBde($this->db);
                $tmp_object->setVarsFromFetchObj($obj);
                $tmp_object->fetch_optionals();
                $obj_ret[] = $this->_cleanObjectDatas($tmp_object);

                $i++;
            }
        } else {
            throw new RestException(503, 'Error when retrieve MoBde list');
        }
        if (!count($obj_ret)) {
            throw new RestException(404, 'No MoBde found');
        }
        return $obj_ret;
    }

    /**
     * List MoPickingLine's
     *
     * Get a list of consumabel MoPickingLine
     *
     * @param string $sortfield Sort field
     * @param string $sortorder Sort order
     * @param int $limit Limit for list
     * @param int $page Page number
     * @param string $sqlfilters Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101')"
     * @return  array                               Array of order objects
     *
     * @throws RestException
     */
    public function getMoPickingLines($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 100, $page = 0, $sqlfilters = '')
    {
        global $conf;

        if (!DolibarrApiAccess::$user->rights->mrp->read) {
            throw new RestException(401);
        }

        $obj_ret = array();
        $tmpobject = new MoPickingLine($this->db);

        $socid = DolibarrApiAccess::$user->socid ? DolibarrApiAccess::$user->socid : '';

        $restrictonsocid = 0; // Set to 1 if there is a field socid in table of object

        // If the internal user must only see his customers, force searching by him
        $search_sale = 0;
        if ($restrictonsocid && !DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) {
            $search_sale = DolibarrApiAccess::$user->id;
        }

        // Build and execute select
        // --------------------------------------------------------------------
        $sql = $this->CreateMoPickingLinesSqlStatement();

        // Example of use $mode
        //if ($mode == 1) $sql.= " AND s.client IN (1, 3)";
        //if ($mode == 2) $sql.= " AND s.client IN (2, 3)";

        if ($tmpobject->ismultientitymanaged) {
            $sql .= ' AND t.entity IN (' . getEntity($tmpobject->element) . ')';
        }
        if ($restrictonsocid && (!DolibarrApiAccess::$user->rights->societe->client->voir && !$socid) || $search_sale > 0) {
            $sql .= " AND t.fk_soc = sc.fk_soc";
        }
        if ($restrictonsocid && $socid) {
            $sql .= " AND t.fk_soc = " . ((int)$socid);
        }
        if ($restrictonsocid && $search_sale > 0) {
            $sql .= " AND t.rowid = sc.fk_soc"; // Join for the needed table to filter by sale
        }
        // Insert sale filter
        if ($restrictonsocid && $search_sale > 0) {
            $sql .= " AND sc.fk_user = " . ((int)$search_sale);
        }
        if ($sqlfilters) {
            $errormessage = '';
            if (!DolibarrApi::_checkFilters($sqlfilters, $errormessage)) {
                throw new RestException(503, 'Error when validating parameter sqlfilters -> ' . $errormessage);
            }
            $regexstring = '\(([^:\'\(\)]+:[^:\'\(\)]+:[^\(\)]+)\)';
            $sql .= " AND (" . preg_replace_callback('/' . $regexstring . '/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters) . ")";
        }

        $sql .= $this->db->order($sortfield, $sortorder);
        if ($limit) {
            if ($page < 0) {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql .= $this->db->plimit($limit + 1, $offset);
        }

        $result = $this->db->query($sql);
        if ($result) {
            $num = $this->db->num_rows($result);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($result);
                $tmp_object = new MoPickingLine($this->db);
                $tmp_object->setVarsFromFetchObj($obj);
                $tmp_object->fetch_optionals();
                $obj_ret[] = $this->_cleanObjectDatas($tmp_object);

                $i++;
            }
        } else {
            throw new RestException(503, 'Error when retrieve MO list');
        }
        if (!count($obj_ret)) {
            throw new RestException(404, 'No MO found');
        }
        return $obj_ret;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore

    /**
     * Clean sensible object datas
     *
     * @param Object $object Object to clean
     * @return  Object              Object with cleaned properties
     */
    protected function _cleanObjectDatas($object)
    {
        // phpcs:enable
        $object = parent::_cleanObjectDatas($object);

        unset($object->rowid);
        unset($object->canvas);

        unset($object->name);
        unset($object->lastname);
        unset($object->firstname);
        unset($object->civility_id);
        unset($object->statut);
        unset($object->state);
        unset($object->state_id);
        unset($object->state_code);
        unset($object->region);
        unset($object->region_code);
        unset($object->country);
        unset($object->country_id);
        unset($object->country_code);
        unset($object->barcode_type);
        unset($object->barcode_type_code);
        unset($object->barcode_type_label);
        unset($object->barcode_type_coder);
        unset($object->total_ht);
        unset($object->total_tva);
        unset($object->total_localtax1);
        unset($object->total_localtax2);
        unset($object->total_ttc);
        unset($object->fk_account);
        unset($object->comments);
        unset($object->note);
        unset($object->mode_reglement_id);
        unset($object->cond_reglement_id);
        unset($object->cond_reglement);
        unset($object->shipping_method_id);
        unset($object->fk_incoterms);
        unset($object->label_incoterms);
        unset($object->location_incoterms);

        // If object has lines, remove $this->db property
        if (isset($object->lines) && is_array($object->lines) && count($object->lines) > 0) {
            $nboflines = count($object->lines);
            for ($i = 0; $i < $nboflines; $i++) {
                $this->_cleanObjectDatas($object->lines[$i]);

                unset($object->lines[$i]->lines);
                unset($object->lines[$i]->note);
            }
        }

        return $object;
    }


    private function CreateMoPickingLinesSqlStatement()
    {
        $tmpobject = new MoPickingLine($this->db);
        $extrafields = new ExtraFields($this->db);

        // Fetch optionals attributes and labels
        $extrafields->fetch_name_optionals_label($tmpobject->table_element);

        // Definition of array of fields for columns
        $selects = $this->getArrOfSqlSelects($tmpobject);


        $sql = 'SELECT t.rowid,t.entity,';
        $sql .= implode(',', $selects);
        $sql .= " FROM " . MAIN_DB_PREFIX . "product_stock as s";
        $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "product as p ON s.fk_product = p.rowid"; // Consum Product Join
        $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "mrp_production as l ON p.rowid = l.fk_product";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . $tmpobject->table_element . " as t ON l.fk_mo = t.rowid";
        if (isset($extrafields->attributes[$tmpobject->table_element]['label']) && is_array($extrafields->attributes[$tmpobject->table_element]['label']) && count($extrafields->attributes[$tmpobject->table_element]['label'])) {
            $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . $tmpobject->table_element . "_extrafields as ef on (t.rowid = ef.fk_object)";
        }
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product AS tp ON t.fk_product = tp.rowid"; // Target Product Join

        $sqlConsumed = "SELECT fk_mrp_production, SUM(qty) AS qty_consumed";
        $sqlConsumed .= " FROM " . MAIN_DB_PREFIX . "mrp_production";
        $sqlConsumed .= " WHERE role = 'consumed'";
        $sqlConsumed .= " GROUP BY fk_mrp_production";

        $sql .= " LEFT JOIN (" . $sqlConsumed . ") as tChild ON l.rowid = tChild.fk_mrp_production";
        $sql .= " WHERE 1 = 1";
        $sql .= " AND t.status IN (1,2,3) AND l.role = 'toconsume' AND p.fk_product_type = 0 AND p.stock > 0";
        $sql .= " HAVING qtytoconsum > 0";

        return $sql;
    }

    /**
     * @param CommonObject $tmpobject
     * @return array
     */
    private function getArrOfSqlSelects(CommonObject $tmpobject): array
    {
        $arrayfields = array();
        foreach ($tmpobject->fields as $key => $val) {
            // If $val['visible']==0, then we never show the field
            if (!empty($val['visible'])) {
                $visible = (int)dol_eval($val['visible'], 1, 1, '1');
                $arrayfields[$key] = array(
                    'label' => $val['label'],
                    'checked' => (($visible < 0) ? 0 : 1),
                    'enabled' => ($visible != 3 && $visible != -2 && dol_eval($val['enabled'], 1, 1, '1')),
                    'position' => $val['position'],
                    'help' => isset($val['help']) ? $val['help'] : ''
                );
                if (!empty($val['sqlSelect'])) {
                    $arrayfields[$key]['sqlSelect'] = $val['sqlSelect'];
                }
            }
        }

        $keys = array_keys($arrayfields);
        $selects = array();
        foreach ($keys as $key) {
            $select = '';
            if (!empty($arrayfields[$key]['sqlSelect'])) {
                $select = $arrayfields[$key]['sqlSelect'];
            } else {
                $select = 't.' . $key;
            }

            $selects[] = $select;
        }
        return $selects;
    }

}
