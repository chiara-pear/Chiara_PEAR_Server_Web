<?php
/**
 * An example of Crtx_PEAR_Channel_Frontend Usage
 *
 * @copyright Copyright � David Shafik and Synaptic Media 2004. All rights reserved.
 * @author Davey Shafik <davey@synapticmedia.net>
 * @link http://www.synapticmedia.net Synaptic Media
 * @version $Id: index.php,v 1.2 2005/03/18 01:35:21 davey Exp $
 * @package
 * @category Crtx
 */

/**
 * Chiara_PEAR_Server_Web Class
 */
require_once 'Chiara/PEAR/Server/Web.php';

$frontend = new Chiara_PEAR_Server_Web('pear.YOURCHANNEL.COM', array('database' => 'mysql://USERNAME:PASSWORD@HOST/DATABASENAME', 'index' => 'index.php', 'admin' => 'admin.php'));
?>
<html>
    <head>
        <title>Chiara_PEAR_Server_Web Example</title>
        <link rel="stylesheet" type="text/css" href="pear_server.css" />
        <?php
            $frontend->showLinks();
        ?>
    </head>
    <body>
        <div id="top">
            <h1><a href="index.php">Chiara_PEAR_Server_Web Example</a></h1>
        </div>
        <div id="menu">
            <?php
                $frontend->showMenu();
            ?>
            <div id="releases">
                <?php
                    $frontend->showLatestReleases();
                ?>
            </div>
        </div>
        <div id="content">    
            <?php
            if (!$frontend->run()) {
                $frontend->welcome();
            }
            ?>
        </div>
    </body>
</html>