<?php
/**
 * Copyright 2005-2011 MERETHIS
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once "../require.php";
require_once $centreon_path . 'www/class/centreon.class.php';
require_once $centreon_path . 'www/class/centreonSession.class.php';
require_once $centreon_path . 'www/class/centreonDB.class.php';
require_once $centreon_path . 'www/class/centreonWidget.class.php';
require_once $centreon_path . 'www/class/centreonACL.class.php';

//load Smarty
require_once $centreon_path . 'GPL_LIB/Smarty/libs/Smarty.class.php';

session_start();

if (!isset($_SESSION['centreon']) || !isset($_REQUEST['widgetId'])) {
    exit;
}
$centreon = $_SESSION['centreon'];
$widgetId = $_REQUEST['widgetId'];

try {
    global $pearDB;

    $db = new CentreonDB();
    $db2 = new CentreonDB("centstorage");
    $dbAcl = $centreon->broker->getBroker() == "ndo" ? new CentreonDB('ndo') : $db2;
    $pearDB = $db;

    $widgetObj = new CentreonWidget($centreon, $db);
    $preferences = $widgetObj->getWidgetPreferences($widgetId);
    $autoRefresh = 0;
    if (isset($preferences['refresh_interval'])) {
        $autoRefresh = $preferences['refresh_interval'];
    }
}    catch (Exception $e) {
    echo $e->getMessage() . "<br/>";
    exit;
}

    if ($centreon->user->admin == 0) {
        $access = new CentreonACL($centreon->user->get_id());
        $grouplist = $access->getAccessGroups();
        $grouplistStr = $access->getAccessGroupsString();
    }

    $path = $centreon_path . 'www/widgets/graph-monitoring/src/';
    $template = new Smarty();
    $template = initSmartyTplForPopup($path, $template, "/", $centreon_path);

    if (isset($preferences['service']) && $preferences['service']) {
        $tab = explode("-", $preferences['service']);
        $res = $db2->query("SELECT host_name, service_description
                                FROM index_data
                                WHERE host_id = ".$db->escape($tab[0])."
                                AND service_id = ".$db->escape($tab[1])."
                               LIMIT 1");
        if ($res->numRows()) {
            $row = $res->fetchRow();
            $host_name = $row["host_name"];
            $service_description = $row["service_description"];
        }
    }

    /*
    * Check ACL
    */

    $acl = 1;
    if (isset($tab[0]) && isset($tab[1]) && $centreon->user->admin == 0) {
        $query = "SELECT host_id
                FROM centreon_acl
                WHERE host_id = ".$dbAcl->escape($tab[0])."
                AND service_id = ".$dbAcl->escape($tab[1])."
                AND group_id IN (".$grouplistStr.")";
        $res = $dbAcl->query($query);
        if (!$res->numRows()) {
            $acl = 0;
        }
    }

    $servicePreferences = "";
    if ($acl == 1) {
        if (isset($preferences['service']) && $preferences['service']) {
            $servicePreferences .= "<div style='overflow:hidden;'><a href='../../main.php?p=204&mode=0&svc_id=" .
                $host_name . ";" . $service_description .
                "' id='linkGraph' target='_parent'><img id='graph' data-src='./src/generateGraph.php?service=" .
                $preferences['service'] . "&tp=" . $preferences['graph_period'] . "&user=" . $centreon->user->user_id .
                "'/></a></div>";
        } else {
            $servicePreferences .= "<div class='update' style='text-align:center;width:350px;'>"._("Please select a resource first")."</div>";
        }
    } else {
        $servicePreferences .= "<div class='update' style='text-align:center;width:350px;'>"._("You are not allowed to reach this graph")."</div>";
    }

    $autoRefresh = $preferences['refresh_interval'];
    $template->assign('widgetId', $widgetId);
    $template->assign('preferences', $preferences);
    $template->assign('autoRefresh', $autoRefresh);
    $template->assign('servicePreferences', $servicePreferences);

    $bMoreViews = 0;
    if ($preferences['more_views']) {
        $bMoreViews = $preferences['more_views'];
    }
    $template->assign('more_views', $bMoreViews);

    $template->display('index.ihtml');
