<?php
/**
 * Chiara PEAR Channel Web frontend. This is a public web interface
 * to a Chiara_PEAR_Server channel.
 *
 * PHP Version 5
 * 
 * @category PEAR
 * @package  Chiara_PEAR_Server_Web
 * @author   Davey Shafik <davey@synapticmedia.net>
 * @author   Brett Bieber <brett.bieber@gmail.com>
 * @license  New BSD
 * @link     http://pear.chiaraquartet.net/index.php?package=Chiara_PEAR_Server_Web
 */

/**
 * Chiara::Chiara_PEAR_Server DB_DataObject Backend
 */
require_once 'Chiara/PEAR/Server/Backend/DBDataObject.php';

/**
 * PEAR::HTML_QuickForm
 */
require_once 'HTML/QuickForm.php';

/**
 * PEAR::Pager
 */
require_once 'Pager/Pager.php';

/**
 * Chiara PEAR Channel Web frontend.
 *
 * Provides a frontend for PEAR channel servers akin to pearweb
 * 
 * Code originally from Davey Shafik's Crtx_PEAR_Server_Frontend
 *
 * @category  Web
 * @package   Chiara_PEAR_Server_Web
 * @author    Davey Shafik <davey@synapticmedia.net>
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2004 David Shafik and Synaptic Media. All rights reserved.
 * @license   New BSD
 * @link      http://pear.chiaraquartet.net/index.php?package=Chiara_PEAR_Server_Web
 */

class Chiara_PEAR_Server_Web extends Chiara_PEAR_Server_Backend_DBDataObject
{

    /**
     * Filename for the file using this package
     *
     * @var string
     */
    protected $index = '';

    /**
     * Filename for the file to serve RSS feeds from
     *
     * @var string
     */
    protected $rss = '';

    /**
     * Filename for the Admin Interface
     *
     * @var string
     */
    protected $admin = '';

    /**
     * A Quickform Instance for use in Search/E-Mail forms
     *
     * @var HTML_QuickForm
     */
    protected $quickForm = null;

    /**
     *  Username for admin user
     */
    protected $user = false;

    /**
     * How many items to show per page
     *
     * This is used in all listings of packages, searches
     * category views and the list of maintainers
     *
     * @var int
     */
    public $per_page = 15;


    /**
     * Number of items to limit the RSS feed too
     *
     * @var int
     */
    public $rss_limit = 5;

    /**
     * E-Mail Address from which e-mails to maintainers should be sent
     *
     * @var string
     */
    public $mailfrom;

    /**
     * Chiara_PEAR_Server_Web Constructor
     *
     * @param string $channel Channel URI
     * @param array  $options An array of options. 
     *              <code>array('database' => $DSN,
     *                          'index' => 'index.php',
     *                          'admin' => 'admin.php');</code>
     */
    public function __construct($channel, $options)
    {
        $this->index = (isset($options['index'])) ? $options['index'] : 'index.php';
        $this->admin = (isset($options['admin'])) ? $options['admin'] : 'admin.php';
        $this->rss   = (isset($options['rss'])) ? $options['rss'] : $this->index;
        
        $this->quickForm = new HTML_QuickForm('channel_frontend');
        parent::__construct($channel, false, $options);
        $this->functions = array(
        'category'    => 'showCategory',
        'categories'  => 'showCategoryList',
        'package'     => 'showPackage',
        'search'      => 'showPackageSearchForm',
        'install'     => 'showInstallPage',
        'faq'         => 'showFaqPage',
        'rss'         => 'showRSS',
        'maintainers' => 'showAccountList',
        'user'        => 'showMaintainerInfo',
        'email'       => 'showMaintainerEmailForm',
        );
        /*
        Special case, catch RSS feed requests and be sure to not continue with
        regular output.
        */
        if (isset($_GET['rss'])) {
            //            header('Content-Type: application/rdf+xml');
            $this->showRSS();
            exit;
        }
        session_start();
        if (isset($_SESSION['_currentUser'])) {
            $this->user = $_SESSION['_currentUser'];
        }
    }

    /**
     * Run the Frontend. This handles all requests automatically
     *
     * @return boolean Whether or not the request is for a valid function.
     */
    public function run()
    {
        foreach ($this->functions as $get_var => $function) {
            if (isset($_REQUEST[$get_var])) {
                call_user_func(array($this, $function));
                return true;
            }
        }
        return false;
    }

    /**
     * Show Category List
     *
     * @return void
     */
    public function showCategoryList()
    {
        if (defined('CHIARA_PEAR_SERVER_WEB_SHOW_EMPTY_CAT')) {
            $show_empty = true;
        } else {
            $show_empty = false;
        }
        $categories = $this->listCategories();
        echo "<h2>Packages</h2>";
        if (sizeof($categories) > 1) {
            echo '<dl>';
            foreach ($categories as $cat) {
                $packages = $this->getCategoryPackages($cat['id'], 4);
                if ((sizeof($packages) == 0) && !$show_empty) {
                    continue;
                }
                $id = (strlen($cat['alias']) == 0) ? $cat['id'] : $cat['alias'];
                echo '<dt><a href="' .$this->index. '?category=' .$id. '&amp;page=1">' .$cat['name']. '</a></dt>';
                foreach ($packages as $pkg) {
                    echo '<dd><a href="' .$this->index. '?package=' .$pkg['package']. '">' .$pkg['package']. '</a></dd>';
                }
            }
            echo '</dl>';
        } else {
            if (sizeof($categories) == 1) {
                echo $this->showPackagesNoCategory();
            }
        }
    }

    /**
     * Display a Categorys Own Page with a list of its packages.
     *
     * @return void
     */
    public function showCategory()
    {
        $per_page = $this->per_page;
        if (!isset($_GET['category'])) {
            echo '<strong>No Category to display</strong>';
            return;
        }
        if ($_GET['category'] == "Default") {
            $_GET['category'] == 0;
            $cat       = new stdClass();
            $cat->name = "Packages";
            $cat->id   = 0;
        } else {
            $cat = DB_DataObject::factory('categories');
            if (is_numeric($_GET['category'])) {
                $cat->id = $_GET['category'];
            } else {
                $cat->alias = $_GET['category'];
            }
            $cat->channel = $this->_channel;
            if (!$cat->find(true)) {
                echo '<strong>No Category to display</strong>';
                return;
            }
        }

        $packages = DB_DataObject::factory('packages');

        $packages->query('SELECT COUNT(package) AS count FROM packages WHERE category_id=' . $cat->id);

        $packages->fetch();
        $count = $packages->count;

        $packages = DB_DataObject::factory('packages');

        if ($count == 0) {
            echo '<h2>' .$cat->name. '</h2>';
            echo '<p><strong class="error">No Packages Found</strong></p>';
            echo '<p><a href="' .$this->index. '?categories">Back to Package List</a></p>';
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] == 1 || !is_numeric($_GET['page'])) {
            $limit = $per_page;
        } else {
            $limit = $per_page * $_GET['page'];
        }
        $start = $limit - $per_page;
        $packages->limit($start, $limit);
        $packages->category_id = $cat->id;
        $packages->channel     = $this->_channel;
        $packages->find();
        echo '<h2>' .$cat->name. '</h2>';
        echo '<table><tr><th colspan="3">';
        $last_on_page = $start + $per_page;
        if ($last_on_page > $count) {
            $last_on_page = $count;
        }
        echo 'Packages ' .($start + 1). ' - ' .($last_on_page). ' of ' .$count;
        echo '</th></tr>';

        $pager =& Pager::factory(array(
        'totalItems'  => $count,
        'perPage'     => $per_page,
        'urlVar'      => 'page',
        'clearIfVoid' => true,
        'extraVars'   => array('category' => $_GET['category']),
        ));

        $pager_links = $pager->getLinks();
        if (!empty($pager_links['all'])) {
            echo '<tr>
                    <td colspan="3" class="pager"><p style="text-align: center;">' .$pager_links['all'].'</p></td>
                </tr>';
        }
        echo '<tr><th>#</th><th>Package Name</th><th>Description</th></tr>';
        $i       = 0;
        $classes = array("dark", "light");
        while ($packages->fetch()) {
            $class = $i % 2;
            
            $i += 1;
            echo '<tr class="'.$classes[$class].'">'
                  . '<td>'.$i.'</td>'
                  . '<td>'
                  . '<a href="'.$this->index.'?package='.$packages->package.'">'
                         .$packages->package.'</a></td>'
                  . '<td>'.nl2br($packages->summary).'</td>'
                  .'</tr>';
        }
        if (!empty($pager_links['all'])) {
            echo '<tr>
                    <td colspan="3" class="pager">
                        <p style="text-align: center;">' .$pager_links['all'].'</p>
                    </td>
                </tr>';
        }
        echo '</table>';
    }

    /**
     * Show Packages that are not in a Category
     *
     * @return void
     */
    public function showPackagesNoCategory()
    {
        $per_page = $this->per_page;

        $packages = DB_DataObject::factory('packages');

        $packages->query('SELECT COUNT(package) AS count FROM packages WHERE category_id=0');
        $packages->fetch();
        $count = $packages->count;

        $packages = DB_DataObject::factory('packages');

        if ($count == 0) {
            echo '<p><strong class="error">No Packages Found.</strong></p>';
            return;
        }

        if (!isset($_GET['page']) || $_GET['page'] == 1 || !is_numeric($_GET['page'])) {
            $limit = $per_page;
        } else {
            $limit = $per_page * $_GET['page'];
        }
        $start = $limit - $per_page;
        $packages->limit($start, $limit);
        $packages->channel = $this->_channel;
        $packages->category_id = 0;
        $packages->find();
        echo '<table><tr><th colspan="3">';
        $last_on_page = $start + $per_page;
        if ($last_on_page > $count) {
            $last_on_page = $count;
        }
        echo 'Packages ' .($start + 1). ' - ' .($last_on_page). ' of ' .$count;
        echo '</th></tr>';

        $pager =& Pager::factory(array(
        'totalItems' => $count,
        'perPage' => $per_page,
        'urlVar' => 'page',
        'clearIfVoid' => true,
        ));

        $pager_links = $pager->getLinks();
        if (!empty($pager_links['all'])) {
            echo '<tr>
                    <td colspan="3"><p style="text-align: center;">' .$pager_links['all'].'</p></td>
                </tr>';
        }
        echo '<tr><th>#</th><th>Package Name</th><th>Description</th></tr>';
        $i = 0;
        $classes = array('dark', 'light');
        while ($packages->fetch()) {
            $class = $i % 2;
            $i += 1;
            echo '<tr class="' .$classes[$class]. '">'
                 . '<td>' .$i. '</td>'
                 . '<td>
                    <a href="' .$this->index. '?package=' .$packages->package. '">' .$packages->package. '</a>'
                 . '</td>'
                 . '<td>' .nl2br($packages->summary). '</td>'
                 .'</tr>';
        }
        if (!empty($pager_links['all'])) {
            echo '<tr>
                    <td colspan="3"><p style="text-align: center;">' .$pager_links['all'].'</p></td>
                </tr>';
        }
        echo '</table>';
    }

    /**
     * Get packages within a single category, optionally limit to $limit packages
     *
     * @param string $category Category ID
     * @param int    $limit    Amount of packages to query for
     * 
     * @return array An array of package rows
     */
    protected function getCategoryPackages($category, $limit = null)
    {
        $pkg = DB_DataObject::factory('packages');
        $pkg->category_id = $category;
        if (!is_null($limit)) {
            $pkg->limit(0, $limit);
        }
        $pkg->channel = $this->_channel;
        $pkg->orderBy('package ASC');
        if (!$pkg->find()) {
            return array();
        }
        while ($pkg->fetch()) {
            $packages[] = $pkg->toArray();
        }
        return $packages;
    }

    /**
     * Show a Packages page
     *
     * @return void
     */
    public function showPackage()
    {
        $pkg             = $this->packageInfo($_GET['package']);
        $subpkg          = DB_DataObject::factory('packages');
        $subpkg->channel = $this->_channel;
        $subpkg->parent  = $_GET['package'];

        $has_sub = $subpkg->find(false);

        if (!$pkg) {
            echo '<strong>No Package to display</strong>';
            return;
        }
        echo '<h2>' .$pkg['package']. '</h2>';
        echo '<ul id="package">';
        echo '<li><a href="' .$this->index. '?package=' .$pkg['package'].'">Main</a></li>';
        if (sizeof($pkg['releases']) > 0) {
            echo '<li><a href="' .$this->index. '?package=' .$pkg['package']. '&amp;downloads">Download</a></li>';
            echo '<li><a href="' .$this->index. '?rss&amp;package=' .$pkg['package']. '">RSS Feed</a></li>';
        }
        $this->showPackageExtras();
        echo '</ul>';
        if (isset($_REQUEST['downloads'])) {
            $this->showPackageDownloads();
            return;
        }
        echo '<h3>Summary</h3>';
        echo '<p>' .nl2br($pkg['summary']). '</p>';
        echo '<h3>License</h3>';
        if (strlen($pkg['licenseuri']) != 0) {
            echo '<p><a href="' .$pkg['licenseuri']. '">' .$pkg['license']. '</a></p>';
        } else {
            echo '<p>' .$pkg['license']. '</p>';
        }
        echo '<h3>Current Release</h3>';
        if (sizeof($pkg['releases']) == 0) {
            echo '<ul><li>No releases have been made yet</li></ul>';
        } else {
            echo '<ul>';
            foreach ($this->getPackageLatestReleases($pkg['package']) as $state => $release) {
                echo '<li><a href="http://' .$this->_channel. '/get/' .$pkg['package']. '-'. $release['version'] .'.tgz">' .$release['version']. '</a> (' .$state. ') was released on ' .$release['date']. '</li>';
            }
            echo '</ul>';
        }
        if ($pkg['summary'] != $pkg['description']) {
            echo '<h3>Description</h3>';
            echo '<p>' .nl2br($pkg['description']). '</p>';
        }

        $devs = $this->listPackageMaintainers($_GET['package']);
        if (sizeof($devs) != 0) {
            echo '<h3>Maintainers</h3>';
            echo '<ul>';
            foreach ($devs as $dev) {
                $dev = $dev->toArray();
                
                $dev['role'] = ucfirst($dev['role']);
                echo "<li><a href='{$this->index}?user={$dev['handle']}'>";
                echo (!empty($dev['name'])) ? $dev['name'] : $dev['handle'];
                echo "</a> ({$dev['role']})</li>";
            }
            echo '</ul>';
        }

        if ($pkg['parent'] != null) {
            echo '<h3>Parent Package</h3>';
            echo "<p><a href='{$this->index}?package={$pkg['parent']}'>{$pkg['parent']}</a></p>";
        }

        if ($has_sub) {
            echo '<h3>Sub-Packages</h3>';
            echo '<ul>';
            while ($subpkg->fetch()) {
                echo "<li><a href='{$this->index}?package={$subpkg->package}'>{$subpkg->package}</a></li>";
            }
            echo '</ul>';
        }
    }

    /**
     * Get the latest releases for a package
     *
     * This function will find the highest stability release
     * as well as the *latest* release (i.e. it will find a 1.0.0-stable
     * release and a 1.0.1-snapshot)
     *
     * @param string $pkg Packaage name
     * 
     * @return array An array of releases
     */
    protected function getPackageLatestReleases($pkg)
    {
        $package = DB_DataObject::factory('releases');
        $states  = array('snapshot', 'devel', 'alpha', 'beta', 'stable');
        foreach ($states as $state) {
            //            $package->query("SELECT version, UNIX_TIMESTAMP(releasedate) AS epoch, DATE_FORMAT(releasedate, '%M %D %Y') AS date,  MAX(releasedate) FROM releases WHERE package='$pkg' AND channel='{$this->_channel}' AND state='$state' GROUP BY package");
            $package->query("SELECT version, UNIX_TIMESTAMP( releasedate )  AS epoch, DATE_FORMAT( releasedate,  '%M %D %Y'  )  AS date, MAX( releasedate )  FROM releases WHERE package='$pkg' AND channel='{$this->_channel}' AND state='$state' GROUP  BY releasedate ORDER  BY releasedate DESC  LIMIT 1");
            while ($package->fetch()) {
                $release[$state] = array('version' => $package->version, 'date' => $package->date, 'epoch' => $package->epoch);
            }
        }

        if (sizeof($release) == 1) {
            return $release;
        }

        $states = array_keys($release);


        for ($i = 0; $i < sizeof($states); $i++) {
            if (isset($states[$i+1]) && $release[$states[$i]]['epoch'] < $release[$states[$i+1]]['epoch']) {
                unset($release[$states[$i]]);
            }
        }


        return array_reverse($release);
    }

    /**
     * Show Package Extras (CVS/Doc/Bugs Links)
     *
     * @return void
     */
    public function showPackageExtras()
    {
        $package          = DB_DataObject::factory('package_extras');
        $package->package = $_GET['package'];
        if (!$package->find(true)) {
            return;
        } else {
            if (strlen($package->docs_uri) > 0) {
                echo '<li><a href="'.htmlspecialchars($package->docs_uri).'">Documentation</a></li>';
            }
            if (strlen($package->bugs_uri) > 0) {
                echo '<li><a href="'.htmlspecialchars($package->bugs_uri).'">Bugs</a></li>';
            }
            if (strlen($package->cvs_uri) > 0) {
                echo '<li><a href="'.htmlspecialchars($package->cvs_uri).'">CVS</a></li>';
            }
        }
    }

    /**
     * Show Package Download/Changelog page
     *
     * @return void
     */
    public function showPackageDownloads()
    {
        $releases = $this->packageInfo($_GET['package']);

        echo "<h3>Downloads</h3>";

        if (!$releases) {
            echo "<p><strong class='error'>No downloads found for '{$_GET['package']}'</strong></p>";
            return;
        }



        echo "<table><tr><th>Version</th><th>Information</th></tr>";
        foreach ($releases['releases'] as $version => $release) {
            $release['releasedate'] = date('F jS Y', strtotime($release['releasedate']));
            if (!isset($_GET['release'])) {
                $_GET['release'] = $version;
            }
            if ($_GET['release'] == $version) {
                echo "<tr>";
                echo "<td valign='top'>$version</td>";
                echo "<td>
                        <h4><a href='http://{$this->_channel}/get/{$_GET['package']}-{$release['version']}.tgz'>Download</a></h4>
                        <p>
                            <span style='font-weight: bold'>Release Date:</span> {$release['releasedate']}
                            <br />
                            <span style='font-weight: bold'>Release State:</span> {$release['state']}
                        </p>
                        <h4>Changelog:</h4>
                        <p>
                            " .nl2br($release['releasenotes']). "
                        </p>
                        <h4>Dependencies</h4>
                        <ul>";
                foreach ($release['deps'] as $dep) {
                    $rel_trans = array('lt' => 'older than %s',
                                       'le' => '%s or older',
                                       'eq' => 'version %s',
                                       'ne' => 'any version but %s',
                                       'gt' => 'newer than %s',
                                       'ge' => '%s or newer',
                                       );

                    $dep_type_desc = array('pkg'    => 'Package',
                                           'ext'    => 'PHP Extension',
                                           'php'    => 'PHP',
                                           'prog'   => 'Program',
                                           'ldlib'  => 'Development Library',
                                           'rtlib'  => 'Runtime Library',
                                           'os'     => 'Operating System',
                                           'websrv' => 'Web Server',
                                           'sapi'   => 'SAPI Backend',
                                           );

                    if (!isset($dep['name'])) {
                        $dep['name'] = '';
                    }

                    if ((!isset($dep['channel'])) || ($dep['type'] == 'php') || ($dep['channel'] == $this->_channel)) {
                        $dep['channel'] = '';
                    } else {
                        $dep['channel'] = 'from ' .$dep['channel'];
                    }

                    if (isset($rel_trans[$dep['rel']])) {
                        if ($dep['type'] == 'php' || $dep['type'] == 'ext') {
                            $dep['version'] = 'version ' .$dep['version'];
                        }
                        $rel = sprintf($rel_trans[$dep['rel']], $dep['version']);
                        printf("<li>%s: %s %s %s</li>", $dep_type_desc[$dep['type']], $dep['name'], $rel, $dep['channel']);
                    } else {
                        printf("<li>%s: %s %s</li>", $dep_type_desc[$dep['type']], $dep['name'], $dep['channel']);
                    }
                    if ($dep['optional'] == 1) {
                        echo ' (optional)';
                    }
                }
                echo "  </ul>
                      </td>";

                echo "</tr>";
            } else {
                echo "<tr>";
                echo "<td><a href='{$this->index}?package={$_GET['package']}&amp;release=$version&amp;downloads'>$version-{$release['state']}</a></td>";
                echo "<td>{$release['releasedate']}</td>";
                echo "</tr>";
            }
        }
        echo '</table>';
    }

    /**
     * Show the Package Search Form
     *
     * @return void
     */
    public function showPackageSearchForm()
    {
        $category = DB_DataObject::factory('categories');
        $handle   = DB_DataObject::factory('handles');

        $category->channel = $this->_channel;
        $category->find();
        $categories    = array('-1' => '');
        $categories[0] = 'No Category';
        while ($category->fetch()) {
            $categories[$category->id] = $category->name;
        }

        $handle->channel = $this->_channel;

        $handle->find();
        $handles = array('-1' => '');
        while ($handle->fetch()) {
            $handles[$handle->handle] = "{$handle->name} ({$handle->handle})";
        }

        echo '<h2>Search Packages</h2>';


        $defaults['match']       = (!isset($_REQUEST['match'])) ? 'all' : $_REQUEST['match'];
        $defaults['name']        = (!isset($_REQUEST['name'])) ? '' : $_REQUEST['name'];
        $defaults['search']      = '1';
        $defaults['category_id'] = (!isset($_REQUEST['category_id'])) ? '-1' : $_REQUEST['category_id'];
        $defaults['maintainer']  = (!isset($_REQUEST['maintainer'])) ? '-1' : $_REQUEST['maintainer'];
        $this->quickForm->setDefaults($defaults);

        $this->quickForm->addElement('header', '', 'Search by Package Name');
        $this->quickForm->addElement('text', 'name', 'Package Name');
        $this->quickForm->addElement('hidden', 'search', 'search');
        $radio[] = HTML_QuickForm::createElement('radio', 'match', '', 'Any Words', 'any');
        $radio[] = HTML_QuickForm::createElement('radio', 'match', '', 'All Words', 'all');
        $this->quickForm->addGroup($radio, 'match_group', 'Match', '', false);
        if (sizeof($categories) > 1) {
            $this->quickForm->addElement('header', '', 'Search by Category');
            $this->quickForm->addElement('select', 'category_id', 'Category', $categories);
        }
        $this->quickForm->addElement('header', '', 'Search by Maintainer');
        $this->quickForm->addElement('select', 'maintainer', 'Maintainer', $handles);
        $this->quickForm->addElement('submit', 'submit', 'Search');
        echo $this->quickForm->toHtml();

        if (isset($_REQUEST['search']) && (strlen($this->quickForm->getSubmitValue('name')) > 0)) {
            if ($this->quickForm->validate()) {
                $this->showPackageSearchResults($this->quickForm->getSubmitValues(), 'package');
            }
        } elseif (isset($_REQUEST['search']) && (isset($_REQUEST['name'])) && (strlen($_REQUEST['name']) != 0)) {
            $this->showPackageSearchResults(array('name' => $_REQUEST['name'], 'match' => $_REQUEST['match']), 'package');
        } elseif (isset($_REQUEST['search']) && ($this->quickForm->getSubmitValue('maintainer')  != -1)) {
            if ($this->quickForm->validate()) {
                $this->showPackageSearchResults($this->quickForm->getSubmitValues(), 'maintainer');
            }
        } elseif (isset($_REQUEST['search']) && (isset($_REQUEST['maintainer'])) && ($_REQUEST['maintainer'] != -1)) {
            $this->showPackageSearchResults(array('maintainer' => $_REQUEST['maintainer']), 'maintainer');
        } elseif (isset($_REQUEST['search']) && ($this->quickForm->getSubmitValue('category_id') != "-1")) {
            if ($this->quickForm->validate()) {
                $this->showPackageSearchResults($this->quickForm->getSubmitValues(), 'category');
            }
        } elseif (isset($_REQUEST['search']) && (isset($_REQUEST['category']))) {
            $this->showPackageSearchResults(array('category_id' => $_REQUEST['category']), 'category');
        }
    }

    /**
     * Show Search Results
     *
     * @param array  $search Result of the search form
     * @param string $type   Type of search to perform
     * 
     * @return void
     */
    public function showPackageSearchResults($search, $type)
    {
        $per_page = $this->per_page;
        switch ($type) {
        case 'package':
            $package = $this->packageSearchByName($search);
            
            $extraVars['name']  = $search['name'];
            $extraVars['match'] = $search['match'];
            break;
        case 'maintainer':
            $package = $this->packageSearchByMaintainer($search);
            
            $extraVars['maintainer'] = $search['maintainer'];
            break;
        case 'category':
            $package = $this->packageSearchByCategory($search);
            
            $extraVars['category'] = $search['category_id'];
            break;
        default:
            return;
        }

        $extraVars['search'] = 1;

        if (!$package) {
            echo '<p><strong class="error">No Results Found for ' .ucfirst($type). ' search</strong></p>';
            return;
        }

        $package->fetch();
        $count = $package->count();

        if (!isset($_GET['page']) || $_GET['page'] == 1 || !is_numeric($_GET['page'])) {
            $limit = $per_page;
        } else {
            $limit = $per_page * $_GET['page'];
        }
        $start = $limit - $per_page;
        $package->limit($start, $limit);
        if (!$package->find()) {
            echo '<p><strong class="error">No Results Found for ' .ucfirst($type). ' search</strong></p>';
            return;
        }
        while ($package->fetch()) {
            $packages[$package->package] = $package->summary;
        }

        $last_on_page = $start + $per_page;
        if ($last_on_page > $count) {
            $last_on_page = $count;
        }

        $pager =& Pager::factory(array(
        'totalItems' => $count,
        'perPage' => $per_page,
        'urlVar' => 'page',
        'clearIfVoid' => true,
        'extraVars' => $extraVars,
        ));

        $pager_links = $pager->getLinks();
        ?>
        <table id="search-results">
            <tr><th colspan="2">Search Results (<?php echo ($start + 1). ' - ' .($last_on_page). ' of ' .$count; ?> results found)</th></tr>
            <tr><th>Package</th><th>Description</th></tr>
            <?php
            if (!empty($pager_links['all'])) {
                echo '<tr>
                        <td class="pager" style="text-align: center;" colspan="2">' .$pager_links['all'].'</td>
                    </tr>';
            }
            $i = 0;
            $classes = array("dark", "light");
            foreach ($packages as $pkg => $summary) {
                $class = $i % 2;
                $i += 1;
                ?>
                <tr class="<?php echo $classes[$class]; ?>">
                    <td><a href="<?php echo $this->index; ?>?package=<?php echo $pkg ?>"><?php echo $pkg; ?></a></td>
                    <td><?php echo $summary; ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
        <?php
    }

    /**
     * Search Packages by Name
     *
     * @param array $search The result array from the search forms HTML_QuickForm
     * 
     * @return object DB_DataObject with which to perform the search
     */
    protected function &packageSearchByName($search)
    {
        $package          = DB_DataObject::factory('packages');
        $package->channel = $this->_channel;
        
        $seperator = (!isset($search['match']) || $search['match'] == 'all') ? 'AND' : 'OR';
        $terms     = explode(' ', $search['name']);
        foreach ($terms as $key => $term) {
            $terms[$key] = "package LIKE '%" .$package->escape($term). "%'";
        }
        $where = implode(' ' .$seperator. ' ', $terms);
        $package->whereAdd($where);
        $package->selectAdd();
        $package->selectAdd('package, summary, COUNT(package) AS count');
        $package->groupBy('package');
        $package->orderBy('package');
        return $package;
    }

    /**
     * Search Packages by Category
     *
     * @param array $search The result array from the search forms HTML_QuickForm
     * 
     * @return object DB_DataObject with which to perform the search
     */
    protected function &packageSearchByCategory($search)
    {
        $package = DB_DataObject::factory('packages');
        
        $package->channel = $this->_channel;
        $package->category_id = $search['category_id'];

        $package->selectAdd();
        $package->selectAdd('package, summary, COUNT(package) AS count');
        $package->groupBy('package');
        $package->orderBy('package');

        return $package;
    }

    /**
     * Search Packages by Maintainer
     *
     * @param array $search The result array from the search forms HTML_QuickForm
     * 
     * @return object DB_DataObject with which to perform the search
     */
    protected function &packageSearchByMaintainer($search)
    {
        $maintainer = DB_DataObject::factory('maintainers');
        $package    = DB_DataObject::factory('packages');

        $maintainer->channel = $this->_channel;
        $maintainer->handle  = $search['maintainer'];

        if (!$maintainer->find()) {
            return false;
        }

        $packages = array();
        while ($maintainer->fetch()) {
            $packages[] = "package='{$maintainer->package}'";
        }

        $package->channel = $this->_channel;

        $where = implode(' OR ', $packages);

        $package->whereAdd($where);
        return $package;
    }


    /**
     * Show list of Maintainers
     *
     * @return void
     */
    public function showAccountList()
    {
        echo "<h2>Maintainers</h2>";
        $maintainers = $this->listMaintainers();
        if (!$maintainers) {
            echo "<p><strong class='error'>No maintainers found</strong></p>";
        }
        ?>
        <table>
            <tr>
                <th>Name</th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        <?php
        $i = 0;
        $classes = array("dark", "light");
        foreach ($maintainers as $maintainer) {
            $class = $i % 2;
            $i += 1;
            ?>
            <tr class="<?php echo $classes[$class]; ?>">
                <td>
                    <?php echo $maintainer->name; ?>
                </td>
                <td>
                    <a href="<?php echo $this->index; ?>?user=<?php echo $maintainer->handle; ?>">More Info</a>
                </td>
                <td>
                    <a href="<?php echo $this->index; ?>?email&amp;handle=<?php echo $maintainer->handle; ?>&amp;list">E-Mail</a>
                </td>
                <td>
                <?php
                if (is_string($maintainer->uri)) {
                    if (!empty($maintainer->uri)) {
                            ?>
                            <a href="<?php echo $maintainer->uri;?>">Website</a>
                            <?php
                    }
                }
                ?>
                </td>
            </tr>
            <?php
        }
        ?>
        </table>
        <?php
    }

    /**
     * Show Maintainer Profile Page
     *
     * @return false
     */
    public function showMaintainerInfo()
    {
        $handle     = DB_DataObject::factory('handles');
        $maintainer = DB_DataObject::factory('maintainers');

        $handle->handle      = $_GET['user'];
        $handle->channel     = $this->_channel;
        $maintainer->handle  = $_GET['user'];
        $maintainer->channel = $this->_channel;
        $maintainer->active  = 1;

        if (!$handle->find(true)) {
            echo "<p><strong class='error'>Maintainer Not Found</strong></p>";
            return;
        }

        $packages = array();
        if ($maintainer->find()) {
            while ($maintainer->fetch()) {
                $packages[] = $maintainer->toArray();
            }
        }

        echo "<h2>" .$handle->name. "</h2>";
        ?>
        <ul>
            <li><a href="<?php echo $this->index; ?>?email&amp;handle=<?php echo $maintainer->handle; ?>">E-Mail</a></li>
        <?php
        if (is_string($handle->uri) && strlen($handle->description) > 0) {
                ?>
            <li><a href="<?php echo $handle->uri;?>">Website</a></li>
                    <?php
        }
        if (is_string($handle->wishlist) && strlen($handle->description) > 0) {
                ?>
                <li><a href="<?php echo $handle->wishlist; ?>" target="_blank">Wishlist</a></li>
                <?php
        }
            ?>
            <li><a href="<?php echo $this->rss;?>?rss&amp;handle=<?php echo $maintainer->handle; ?>">RSS Feed</a></li>
        </ul>
        <?php
        if (is_string($handle->description) && strlen($handle->description) > 0) {
            echo "<h3>About</h3>";
            echo "<p>$handle->description</p>";
        }
        if (sizeof($packages) > 0) {
            echo "<h3>Maintains These Packages</h3>";
            echo "<ul>";
            foreach ($packages as $package) {
                echo '<li><a href="' .$this->index. '?package=' .$package['package']. '">' .$package['package']. '</a> (' .$package['role']. ')</li>';
            }
            echo "</ul>";
        }
    }

    /**
     * Show the $how_many Latest Releases for the channel
     *
     * @param int $how_many How many releases to display
     * 
     * @return void
     */
    public function showLatestReleases($how_many = null)
    {
        if (is_null($how_many)) {
            $how_many = $this->rss_limit;
        }
        $release          = DB_DataObject::factory('releases');
        $release->channel = $this->_channel;
        $release->orderBy('releasedate DESC');
        $release->limit(0, $how_many);

        echo "<h2>Latest Releases</h2>";

        if (!$release->find()) {
            echo "<p><strong>No releases yet</strong></p>";
            return;
        }

        echo "<dl>";
        while ($release->fetch()) {
            echo "<dt><a href='{$this->index}?package={$release->package}&amp;release={$release->version}&amp;downloads'>{$release->package} {$release->version} ({$release->state})</a></dt>";
            $notes = substr($release->releasenotes, 0, 40);

            if (strlen($notes) < strlen($release->releasenotes)) {
                $notes = substr($notes, 0, strrpos($notes, ' ')) . '...';
            }
            $date = date('F jS Y', strtotime($release->releasedate));
            echo "<dd><strong>$date</strong>: $notes</dd>";
        }
        echo "</dl>";
        echo "<p><a href='{$this->rss}?rss&amp;latest'>Syndicate This</a></p>";

    }


    /**
     * Show Maintainer E-Mail form
     *
     * @return void
     */
    public function showMaintainerEmailForm()
    {
        $handle          = DB_DataObject::factory('handles');
        $handle->channel = $this->_channel;

        echo "<h2>E-Mail Maintainer</h2>";
        if (isset($_GET['handle'])) {
            $handle->handle = $_GET['handle'];
            if (!$handle->find(true)) {
                echo "<p><strong class='error'>Maintainer does not exist</strong></p>";
                return;
            }
            $this->quickForm->setDefaults(array('to' => $handle->name));
            $this->quickForm->addElement('static', 'to', 'To:');
            $this->quickForm->addElement('hidden', 'handle', $handle->handle);
        } else {
            if (!$handle->find()) {
                echo "<p><strong class='error'>No Maintainers Found</strong></p>";
                return;
            }
            $handles = array(0 => '');
            while ($handle->fetch()) {
                $handles[$handle->handle] = $handle->name;
            }
            $this->quickForm->addElement('select', 'handle', 'To:', $handles);
        }

        $this->quickForm->addElement('hidden', 'email', 1);
        $this->quickForm->addElement('text', 'from', 'From: ', array('size' => 40));
        $this->quickForm->addElement('text', 'subject', 'Subject: ', array('size' => 40));
        $this->quickForm->addElement('textarea', 'message', 'Message: ', array('cols' => 40, 'rows' => 8));
        $this->quickForm->addElement('submit', 'submit', 'Send E-Mail');

        $this->quickForm->addRule('handle', 'Please choose a Maintainer to e-mail', 'required');
        $this->quickForm->addRule('handle', 'Please choose a Maintainer to e-mail', 'minlength', 2);
        $this->quickForm->addRule('from', 'Please enter your e-mail address', 'required');
        $this->quickForm->addRule('subject', 'Please enter a subject', 'required');
        $this->quickForm->addRule('message', 'Please enter a message', 'required');

        if ($this->quickForm->validate()) {
            $handle          = DB_DataObject::factory('handles');
            $handle->channel = $this->_channel;
            $handle->handle  = $this->quickForm->getSubmitValue('handle');
            $handle->debugValue(1);
            if (!$handle->find(true)) {
                echo "<p><strong class='error'>Maintainer does not exist</strong></p>";
                return;
            }
            if (!$this->mailfrom) {
                $channel = $this->channelInfo();
                $from    = $channel['summary'] . '<noreply@' .$channel['channel'];
            } else {
                $from = $this->mailfrom;
            }
            mail($handle->email, $this->quickForm->getSubmitValue('subject'), $this->quickForm->getSubmitValue('message'), 'From: ' .$this->quickForm->getSubmitValue('from'));
            echo "<p><strong>Thank you. {$handle->name} has been e-mailed with your message.</strong></p>";
            return;
        }

        echo $this->quickForm->toHtml();
    }

    /**
     * Show A page with instructions on installing and using the channel
     *
     * @return void
     */
    public function showInstallPage()
    {
        $channel = $this->channelInfo();
        ?>
        <h2>Installation</h2>
        <p>
            Installing packages from <?php echo $channel['channel']; ?> is easy. Just follow
            the simple steps below.
        </p>
        <p>
            <strong>Note: </strong> You need a working PEAR 1.4 environment. For more information
            on PEAR and how to install PEAR, see <a href="<?php echo $this->index;?>?faq#pear">our
            FAQ</a>
        </p>
        <ol>
            <li>
                First, add the channel server using:
                <br />
                <code>pear channel-discover <?php echo $channel['channel']; ?></code>
            </li>
            <li>
                Once this is complete, you can install any of our packages using:
                <br />
                <code>pear install <?php echo $channel['alias']; ?>/<strong>some_package_name</strong></code>
            </li>
        </ol>
        <?php
    }

    /**
     * Display the FAQ Page
     *
     * @return void
     */
    public function showFaqPage()
    {
        ?>
        <h2>Frequently Asked Questions</h2>
        <ul>
            <li><a href="#pear">What is PEAR?</a></li>
            <li><a href="#pear-install">How do I install PEAR?</a></li>
        </ul>
        <h3>Answers</h3>
        <h4 id="pear">What is PEAR?</h4>
        <p>
            PEAR is a framework and distribution system for reusable PHP components. More information about PEAR can be found in the PEAR <a href="http://pear.php.net/manual/en/about-pear.php">manual</a> and the PEAR <a href="http://pear.php.net/manual/en/faq.php">FAQ</a>.
        </p>
        <h4 id="pear-install">How do I install PEAR?</h4>
        <p>
            Full installation instructions for PEAR can be found in <a href="http://pear.php.net/manual/en/installation.php">the PEAR manual</a>.
        </p>
        <?php
    }

    /**
     * Show an RSS feed
     *
     * Displays RSS feeds for latest channel releases,
     * latest releases for a specific package and latest
     * releases for a specific maintainer
     *
     * @return void
     */
    public function showRSS()
    {
        $release          = DB_DataObject::factory('releases');
        $release->channel = $this->_channel;
        $release->limit(0, $this->rss_limit);
        $release->orderBy('releasedate DESC');
        $channel = $this->channelInfo();
        
        $rdf = '<?xml version="1.0" encoding="iso-8859-1"?>
                <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">
                    <channel rdf:about="http://' .$this->_channel. '">
                        <link>http://' .$this->_channel. '/' .$this->index. '</link>
                        <dc:creator>http://' .$this->_channel. '/' .$this->index. '</dc:creator>
                        <dc:language>en-US</dc:language>
                        <title>' .$channel['summary']. '</title>
                        <description>The latest releases for ' .$channel['summary']. '</description>
                        <items>
                            <rdf:Seq>';


        if (isset($_GET['handle'])) {
            $release->maintainer = $_GET['handle'];
        } elseif (isset($_GET['package'])) {
            $release->package = $_GET['package'];
        } elseif (!isset($_GET['latest'])) {
            header('HTTP/1.1 404 File Not Found');
            return;
        }

        if (!$release->find(false)) {
            header('HTTP/1.1 404 File Not Found');
            return;
        }

        while ($release->fetch()) {
            $releases[] = clone $release;
        }


        foreach ($releases as $rel) {
            $rdf .= '<rdf:li rdf:resource="http://' .$this->_channel. '/' .$this->index. '?package=' .$rel->package. '&amp;release=' .$rel->version. '&amp;downloads"/>';
        }
        $rdf .= '
                            </rdf:Seq>
                        </items>
                    </channel>';
        foreach ($releases as $rel) {
            $rdf .= '
                    <item rdf:about="http://' .$this->_channel. '/' .$this->index. '?package=' .$rel->package. '&amp;release=' .$rel->version. '&amp;downloads">
                        <title>' .$rel->package. ' ' .$rel->version. ' (' .$rel->state. ')</title>
                        <link>http://' .$this->_channel. '/' .$this->index. '?package=' .$rel->package. '&amp;=' .$rel->version. '&amp;downloads</link>
                        <description>
                            ' .$rel->releasenotes. '
                        </description>
                        <dc:date>' .date('c', strtotime($rel->releasedate)). '</dc:date>
                    </item>';
        }
        $rdf .= '</rdf:RDF>';
        header('Content-Type: text/xml');
        echo $rdf;
    }

    /**
     * Output the <link> tags for the RSS feeds on the correct pages
     *
     * @return void
     */
    public function showLinks()
    {
        $channel = $this->channelInfo();
        if (isset($_GET['user'])) {
            $handle = $this->getMaintainer($_GET['user']);
            ?>
            <link rel="alternate" type="application/rss+xml" href="<?php echo $this->index;?>?rss&amp;handle=<?php echo $_GET['user'];?>" title="<?php echo $handle->name ?>'s Package Releases" />
            <?php
        } elseif (isset($_GET['package'])) {
            ?>
            <link rel="alternate" type="application/rss+xml" href="<?php echo $this->index;?>?rss&amp;package=<?php echo $_GET['package'];?>" title="<?php echo $_GET['package']; ?> Latest Releases" />
            <?php
        }
        ?>
            <link rel="alternate" type="application/rss+xml" href="<?php echo $this->index;?>?rss&amp;latest" title="<?php echo $channel['summary'];?> Latest Releases" />
        <?php
    }

    /**
     * Return an array of menu items
     *
     * @return array
     */
    public function getMenu()
    {
        $menu = array(
        'Channel' => array(
        $this->index => 'Home',
        $this->index . '?install' => 'Installation',
        $this->index . '?faq' => 'FAQ',
        $this->admin => 'Admin',
        ),
        'Packages' => array(
        $this->index . '?categories' => 'List Packages',
        $this->index . '?search' => 'Search Packages',
        ),
        'Maintainers' => array(
        $this->index . '?maintainers' => 'List Maintainers',
        $this->index . '?email' => 'E-Mail Maintainer',
        )
        );
        return $menu;
    }

    /**
     * Show the Menu
     *
     * @return void
     */
    public function showMenu()
    {
        foreach ($this->getMenu() as $title => $menu) {
            echo "<h2>$title</h2>";
            echo '<ul>';
            foreach ($menu as $url => $name) {
                echo '<li><a href="' .$url. '">' .$name. '</a></li>';
            }
            echo '</ul>';
        }
    }

    /**
     * Show a Default Welcome message
     *
     * @return void
     */
    public function welcome()
    {
        $channel = $this->channelInfo();
        ?>
        <h2>Welcome!</h2>
        <p>
            Welcome to the <?php echo $channel['summary']; ?>. From
            here you can browse and search the packages we offer and
            download releases to install.
        </p>
        <h3>Using our Channel</h3>
        <p>
            To add this channel to your PEAR install, use:
        </p>
        <p>
            <code>
                pear channel-discover <?php echo $channel['channel']; ?>
            </code>
        </p>
        <p>
            Then you will be able to install our packages by using:
        </p>
        <p>
            <code>pear install <?php echo $channel['alias']; ?>/<strong>package_name</strong></code>
        </p>
        <?php
    }

    /**
     * Determine if a user is currently logged in (or previously logged in) to
     * the admin
     *
     * @return boolean
     */
    public function isMaintainer()
    {
        if ($this->user) {
            return true;
        } else {
            return false;
        }
    }
}

?>