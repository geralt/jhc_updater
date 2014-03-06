<?php
# https://code.google.com/p/textpattern/source/browse/development/4.x-plugin-template/zem_plugin_example.php

$plugin['name'] = 'jhc_updater';
// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
$plugin['allow_html_help'] = 0;
$plugin['version'] = '0.1';
$plugin['author'] = 'Jorge Hoya Cicero';
$plugin['author_uri'] = 'http://www.jorgehoya.es/';
$plugin['description'] = 'Autoupdater for Textpattern';
// Plugin load order:
// The default value of 5 would fit most plugins, while for instance comment
// spam evaluators or URL redirectors would probably want to run earlier
// (1...4) to prepare the environment for everything else that follows.
// Values 6...9 should be considered for plugins which would work late.
// This order is user-overrideable.
$plugin['order'] = 5;
// Plugin 'type' defines where the plugin is loaded
// 0 = public       : only on the public side of the website (default)
// 1 = public+admin : on both the public and non-AJAX admin side
// 2 = library      : only when include_plugin() or require_plugin() is called
// 3 = admin        : only on the non-AJAX admin side
// 4 = admin+ajax   : only on admin side
// 5 = public+admin+ajax   : on both the public and admin side
$plugin['type'] = 3;
// Plugin 'flags' signal the presence of optional capabilities to the core plugin loader.
// Use an appropriately OR-ed combination of these flags.
// The four high-order bits 0xf000 are available for this plugin's private use.
// if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
// if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events
//$plugin['flags'] = PLUGIN_HAS_PREFS | PLUGIN_LIFECYCLE_NOTIFY;
//
// Plugin 'textpack' is optional. It provides i18n strings to be used in conjunction with gTxt().
/**
$plugin['textpack'] = <<< EOT
#@public
#@language en-gb
zem_greeting => Hello,
zem_default_name => Alice
#@language de-de
zem_greeting => Hallo,
zem_default_name => Elise
#@test
#@language en-gb
zem_type_something => Type something:
#@language de-de
zem_type_something => Schreibe etwas:
EOT;
/**/

if (!defined('txpinterface')) @include_once('out\zem_tpl.php');
if (0) {
?>
# --- BEGIN PLUGIN HELP ---

Autoupdater for Textpattern (TXP)
In process...

# --- END PLUGIN HELP ---
<?php
}
# --- BEGIN PLUGIN CODE ---

/**
 * 
 */
function jhc_updater_panel() 
{
    //global $txpcfg, $txpprefs;

    $act = strtolower(gps("jhc_event"));
    pagetop("Textpattern - Updater");
    $dbversion =$dbVersion = get_pref('version'); // $dbversion necessary into _update.php
    // for testing
    //$dbVersion = $dbVersion = '4.4.1';
    
    echo "<div id='jhc-div'>";
    echo form(
            tag("Auto update panel", "h3")
            );
            /*
            graf(gTxt('zem_type_something').
                    fInput("text", "something", $something, "edit", "", "", "20", "1").
                    fInput("submit", "do_something", "Go", "smallerbox").
                    eInput("test").sInput("step_a")
            ," style=\"text-align:center\"")
            /**/
    if ( empty($act)) {
        //echo tag('Your installed version is: ' . $dbVersion,'p');
        $newVersion = jhc_check_version($dbVersion);
        if ( $newVersion['update']) {
            echo tag(
                'There is a new updatable stable version (' . $newVersion['version'] . '). You can <a href="'.$newVersion['url'].'" class="jhc-button">download it</a> or <a href="./?event=jhc_updater_panel&jhc_event=update" class="jhc-publish jhc-install">install it</a>.',
                 'p');
        }
        else { 
            echo tag('There\'s no update for your installed version ' . $dbVersion ,'p'); 
        }
    }
    else {
        switch($act) {
            case 'update':
                $newVersion = jhc_check_version($dbVersion);
                if ( $newVersion['update']) {
                    echo tag('updating....','p');
                    jhc_updater_step_by_step();
                }
                else {
                    echo tag('There\'s no update for your installed version ' . $dbVersion ,'p');
                }      
            break;   
        }
    }
    echo "</div>";
}
/**
 * 
 * @return boolean
 */
function jhc_updater_step_by_step() {
    
    $codeUrl = 'http://textpattern.com/file_download/92/textpattern-4.5.5.zip';    
    $tmpFolder = txpath . DS . '..' . DS . 'jhc_updater' .DS .'tmp';
    $tmpFolder = get_pref('tempdir', $tmpFolder);
    if ( !is_dir($tmpFolder)) {
        echo tag("Error: we couldn't find working temporal folder: '$tmpFolder' ", "p");
        return false;
    }
    // folder where we unzip the downloaded file.
    $actualSource = txpath . DS . '..' . DS;    
    // backup folder
    $outFolder = $tmpFolder . DS . 'updater' . DS;
    // actual installation's backup file (before update)
    $backFile = $tmpFolder . DS .'txp_actual_install.zip';
    $tmpFileName = $tmpFolder . DS . 'downloaded_file.zip';

    
    if (class_exists('ZipArchive')) {    
        
        try {
            # 1. download file with the last code
            if ( jhc_updater_step_download($codeUrl, $tmpFileName) ) {
                /**/
                //if(!is_dir($tmpFolder)) { mkdir($tmpFolder, 0750);}
                if(!is_dir($outFolder)) { mkdir($outFolder, 0750);}
                # backup actual installation
                if (jhc_updater_step_backup($actualSource, $backFile)) { 
                    # 3. unzip downloaded file
                    echo tag("3. Extracting downladed file " . htmlspecialchars($tmpFileName) , "p", ' class="text-left"');
                    $z = new ZipArchive();
                    if ($z->open($tmpFileName) === TRUE) {
                        $z->extractTo($outFolder);
                        $z->close();
                        # 4. copy new installation
                        # TODO: estract folder name where is the new code
                        $entry = '';
                        $d = dir($outFolder);
                        while (empty($entry) && false !== ($entry = $d->read())) {
                            if ( in_array($entry, array('.', '..')) ){
                                $entry = '';
                            }
                         }
                        $d->close();
                        $newSrcFolder = $entry;
                        if (!empty($newSrcFolder)) {
                            echo tag('4. Coping new files into ' . htmlspecialchars($actualSource) . ' from ' . htmlspecialchars($outFolder . $newSrcFolder), 'p', ' class="text-left"');
                            echo ( jhc_updater_copy_directory($outFolder . $newSrcFolder, $actualSource) ) ? 'sucsess' : 'unsuccess';
                        }
                        else {
                            echo tag('ERROR: We could\'t find temporal folder with new code['.$outFolder . $newSrcFolder.  ']. Process halted.' , 'p');
                        }
                        # clean all
                        @unlink($tmpFileName);
                        rrmdir($outFolder);
                        #@unlink($backFile); # ¿sure??
                        
                        # update database: must be new include because files has been changed.
                        # doesnt't work!!!
                        include txpath.'/lib/constants.php';
                        define('TXP_UPDATE', 1);
                        include txpath.'/update/_update.php';
                        # change to default theme
                        set_pref('theme_name', 'classic');
                    }
                    else {
                        echo tag("ERROR: We could't open zip file. Process halted." , "p");
                    }
                }
                else {
                    echo tag("ERROR: We could't do backup of your actual installation. Process halted" , "p");
                }
            }
        } catch ( Exception  $e) { var_dump($e);} 
    }
}

/**/
// hack for version 4.5.5
if ( !function_exists('assert_system_requirements')) {
    
    function assert_system_requirements()
    {
        include txpath.'/lib/constants.php';
        if (version_compare(REQUIRED_PHP_VERSION, PHP_VERSION) > 0)
        {
            txp_die('This server runs PHP version '.PHP_VERSION.'. Textpattern needs PHP version '. REQUIRED_PHP_VERSION. ' or better.');
        }
    }
}
/**/
/**
 * 
 * @param type $codeUrl
 * @param type $tmpFileName
 * @return boolean
 */
function jhc_updater_step_download($codeUrl, $tmpFileName) 
{
    echo tag('1. Downloading file with the last code from: ' . $codeUrl, 'p', ' class="text-left"');
    if ( jhc_download_file ($codeUrl, $tmpFileName) ) {
        echo tag('Code downloaded correctly from ['.$codeUrl.'] into [' .$tmpFileName.']', 'p');  
        return true;
    }
    else { 
        echo tag('Error: we couldn\'t download code from ['.$codeUrl.'] into [' .$tmpFileName.']', 'p');  
        return false;
    }  
}
/**
 * 
 * @param type $actualSource
 * @param type $backFile
 * @return boolean
 */
function jhc_updater_step_backup($actualSource, $backFile)
{
    echo tag('2. Performing backup of actual installation files [' . $actualSource . '] into [' . htmlspecialchars($backFile). ']' , 'p', ' class="text-left"');
    // antes de mover, hacemos una copia de seguridad de la actual instalacion.
    jhc_compress_folder($actualSource, '', $backFile, null);
    # check if file exists
    if (file_exists($backFile)) { return true;}
    return false;
    
}
/**
 * 
 * @param type $dbVersion
 * @return type
 */
function jhc_check_version( $dbVersion = null) 
{
    // TODO: cache download page.
    if (is_null($dbVersion)) $dbVersion = get_pref('version');
    $out = array('update' => FALSE, 'version' => $dbVersion, 'url' => '');
    //var_dump($out);
    //echo tag('Actual version from database: ' . $dbVersion,'p');
    $url = 'http://textpattern.com/';
    
    $srcPage = jhc_url_get($url);
    //echo tag($srcPage, 'textarea');
    if ( !empty($srcPage)) {
        preg_match_all('@<p class=\"versionNumber\">(.*)<\/p>@iU', $srcPage, $a);
        if (is_array($a) && !empty($a)) {
            $out['version'] = $a[1][0];            
            //var_dump($a);
            if (version_compare($out['version'], $dbVersion) > 0) {
                preg_match_all('@<a class=\"downloadButton\" href="(.*)">(.*)<\/a>@iU', $srcPage, $b);
                $out['update'] = TRUE;
                $out['url'] = @$b[1][0];
            }
        }
    }
    return $out;
}
/**
 * 
 * @param type $src
 * @param type $folder
 * @param type $backFile
 * @param type $zipResource
 * @throws Exception
 */
function jhc_compress_folder($src, $folder='', $backFile, $zipResource = null ) 
{
    $debug = FALSE;
    if($debug) var_dump ( func_get_args());
    if ($handle = opendir($src))  
    {
        if (!is_null($zipResource)) {
            $zip = $zipResource;   
        }
        else {            
            $zip = new ZipArchive();
            if ($zip->open($backFile, ZIPARCHIVE::CREATE)!==TRUE) {
                throw new Exception('compress_dir(): cannot open file ' . $backFile);
            }
        }
        while (false !== ($file = readdir($handle))) 
        {
            if( $file == '.' || $file == '..') {continue;}
            if ( is_file ($src . $file)) {
                if($debug) echo $file . ' is a file: ' . $src. $folder . $file . '<br>';
                $zip->addFile($src. $file, $folder . $file);
            }
            else if (is_dir($src . $file)) {
                if($debug) echo $file . ' is a folder: ' . $file . '/'.'<br>';
                $zip->addEmptyDir( $folder. $file . '/');
                jhc_compress_folder($src . $file.DS, $folder .$file.DS , $backFile, $zip);
            }
        }
        closedir($handle);
        if (is_null($zipResource)) {
            $zip->close();
            if ( is_readable($backFile) ) {
                chmod($backFile, 0640);
            }
        }
    }
}
/**
 * 
 * @param type $source
 * @param type $destination
 * @return type
 */
function jhc_updater_copy_directory( $source, $destination ) 
{
// http://stackoverflow.com/questions/2050859/copy-entire-contents-of-a-directory-to-another-using-php
/*
    // Falta ajustar los permisos. Si miramos cómo lo hace WP vemos en wp-admin/includes/file.php
    // Set the permission constants if not already set.
	if ( ! defined('FS_CHMOD_DIR') )
		define('FS_CHMOD_DIR', ( fileperms( ABSPATH ) & 0777 | 0750 ) );
	if ( ! defined('FS_CHMOD_FILE') )
		define('FS_CHMOD_FILE', ( fileperms( ABSPATH . 'index.php' ) & 0777 | 0640 ) );
 */
    $modeDir = 0750;
    $modeFile = 0640;
    $status = TRUE;
    if ( is_dir( $source ) ) {
        if(!is_dir($destination)) { 
            if(!mkdir( $destination, $modeDir, TRUE)){
                $status = false;
            }
        }
        $directory = dir( $source );
        while ( FALSE !== ( $readdirectory = $directory->read() ) && $status === TRUE) {
            if ( $readdirectory == '.' || $readdirectory == '..' ) {
                continue;
            }
            $PathDir = $source . DS . $readdirectory; 
            if ( is_dir( $PathDir ) ) {
                $status = jhc_updater_copy_directory( $PathDir, $destination . DS . $readdirectory );
            } 
            else if ( is_file($PathDir)) {
                $status = copy( $PathDir, $destination . DS . $readdirectory );
                @chmod($destination . DS . $readdirectory, $modeFile);
            }
        }
        $directory->close();
    }else {
        $status = copy( $source, $destination );
    }
    return $status;
}
/**
 * 
 * @param type $url
 * @param type $file
 * @return boolean
 */
function jhc_download_file($url, $file)
{
    if ( !function_exists("curl_init")) { return false; }
    if ( empty($url) || empty($file)) { return false; }

    if ( $ch = curl_init()) {
        if ( $fp = fopen($file, "w") ) {
            if( !curl_setopt($ch, CURLOPT_URL, $url) ) {
                fclose($fp);
                curl_close($ch); // to match curl_init()
            }
            else {
                curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);            
                curl_setopt($ch, CURLOPT_HEADER, FALSE);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
                curl_setopt($ch, CURLOPT_REFERER, $url);
                curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
                if( curl_exec($ch) ) { 
                    curl_close($ch);
                    fclose($fp);
                    return (is_file($file) && is_readable($file)) ? true : false;
                }
                #curl_close($ch);
                fclose($fp);
            }
        }
        else { 
            curl_close($ch);
        }
    }
    return false;
}
/**
 * 
 * @param type $url
 * @return string
 */
function jhc_url_get($url) 
{
    $out = '';
    if ( !function_exists("curl_init")) { return $out; }
    if ( empty($url)) { return $out; }

    if ( $ch = curl_init()) {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_USERAGENT, '"Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.8.1.11) Gecko/20071204 Ubuntu/7.10 (gutsy) Firefox/2.0.0.11');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);            
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2)        ;
        curl_setopt($ch, CURLOPT_REFERER, $url);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, false);
        $out = curl_exec($ch);
        curl_close($ch);
    }
    return $out;
}
/**
 * 
 * @param type $dir
 */
function rrmdir($dir) 
{
    // http://us3.php.net/manual/es/function.rmdir.php
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir."/".$object) == "dir") 
                    @rrmdir($dir."/".$object); 
                else 
                    @unlink($dir."/".$object);
            }
        }
        reset($objects);
        rmdir($dir);
    }
 }
/**
 * 
 */
function jhc_updater_css_and_js()
{
    echo <<<EOF
<style>
    #jhc-div {
        text-align: center;
        margin: 0 3em 0 0;
    }
    .text-left { text-align: left;}
    .text-center { text-align: center;}
    .text-right { text-align: right;}
    a.jhc-publish {
        color: #990000;
        font-weight: bold;
    }
    a.jhc-button {
        
   }
</style>
<script type="text/javascript">
    $(document).ready(function(){
        $('.jhc-install').click(function(){
            //$( "#jhc-div" ).load( "/jhc_updater/test.php" );
            //return false;
        });
    });
</script>
EOF;
}

if (@txpinterface == 'admin') 
{
	add_privs('jhc_updater_panel', '1');
	// CSS and JS
    register_callback('jhc_updater_css_and_js', 'admin_side', 'head_end'); 
	register_tab("extensions", "jhc_updater_panel", "Auto updater");
	register_callback("jhc_updater_panel", "jhc_updater_panel");
}

/**
 * TODO:
 * internacionalization
 */