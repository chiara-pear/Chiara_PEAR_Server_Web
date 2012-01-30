<?php
/**
 * An example of Chiara_PEAR_Server_Web Usage
 *
 * @copyright Copyright David Shafik and Synaptic Media 2004. All rights reserved.
 * @author Davey Shafik <davey@synapticmedia.net>
 * @link http://www.synapticmedia.net Synaptic Media
 * @version $Id: index.php,v 1.2 2005/03/18 01:35:21 davey Exp $
 * @package
 * @category PEAR
 */

/**
 * Chiara_PEAR_Server_Web Class
 */
require_once 'Chiara/PEAR/Server/Web.php';

$frontend = new Chiara_PEAR_Server_Web('pear.YOURCHANNEL.COM', array('database' => 'mysql://USERNAME:PASSWORD@HOST/DATABASENAME', 'index' => 'index.php', 'admin' => 'admin.php'));
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml" lang="en" xml:lang="en">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
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