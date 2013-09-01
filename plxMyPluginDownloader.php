<?php
/**
 * Plugin plxMyPluginDownloader
 *
 * @author	Stephane F
 **/

class plxMyPluginDownloader extends plxPlugin {

	/**
	 * Constructeur de la classe
	 *
	 * @param	default_lang	langue par défaut
	 * @return	stdio
	 * @author	Stephane F
	 **/
	public function __construct($default_lang) {

        # appel du constructeur de la classe plxPlugin (obligatoire)
        parent::__construct($default_lang);

		# droits pour accèder à la page config.php
		$this->setConfigProfil(PROFIL_ADMIN);

		# droits pour accèder à la page admin.php
		$this->setAdminProfil(PROFIL_ADMIN);

		# déclaration des hooks
        $this->addHook('AdminSettingsPluginsTop', 'AdminSettingsPluginsTop');
        $this->addHook('AdminPrepend', 'AdminPrepend');
        $this->addHook('AdminTopBottom', 'AdminTopBottom');
        $this->addHook('AdminTopEndHead', 'AdminTopEndHead');

    }

    public function onActivate() {
    	if(!is_dir(PLX_PLUGINS.'plxMyPluginDownloader/cache')) {
    		mkdir(PLX_PLUGINS.'plxMyPluginDownloader/cache',0755,true);
    	}
    }

	public function AdminTopEndHead() {
		echo '<link rel="stylesheet" type="text/css" href="'.PLX_PLUGINS.'plxMyPluginDownloader/style.css" />'."\n";
	}

	public static function is_cURL() {
		return in_array("curl", get_loaded_extensions());
	}

	public static function getRemoteFileContent($remotefile){
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_URL, $remotefile);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($curl);
		curl_close($curl);
		if ($data !== false){
			return $data;
		}
		return false;
	}

	public static function is_RemoteFileExists($remotefile) {

		return true;
  		// Version 4.x supported
    	$curl   = curl_init($remotefile);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_FAILONERROR, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, Array("User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.15) Gecko/20080623 Firefox/2.0.0.15") ); // request as if Firefox
		curl_setopt($curl, CURLOPT_NOBODY, true);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		$connectable = curl_exec($curl);
		curl_close($curl);
		return $connectable;

	}

	public static function downloadRemoteFile($remotefile, $destination) {

		if($fp = fopen($destination, 'w')) {
			$curl = curl_init($remotefile);
			curl_setopt($curl, CURLOPT_FILE, $fp);
			curl_exec($curl);
			curl_close($curl);
			fclose($fp);
		}
		else return false;

		return (is_file($destination) AND filesize($destination)>0);

	}

	public static function getRepository($filename) {

		$array=array();
		# Mise en place du parseur XML
		$data = implode('',file($filename));
		$parser = xml_parser_create(PLX_CHARSET);
		xml_parser_set_option($parser,XML_OPTION_CASE_FOLDING,0);
		xml_parser_set_option($parser,XML_OPTION_SKIP_WHITE,0);
		xml_parse_into_struct($parser,$data,$values,$iTags);
		xml_parser_free($parser);
		# Récupération des données xml
		if(isset($iTags['plugin'])) {
			$nb = sizeof($iTags['name']);
			for($i = 0; $i < $nb; $i++) {
				$name = plxUtils::getValue($values[$iTags['name'][$i]]['value']);
				if($name!='') {
					$array[$name]['title'] = plxUtils::getValue($values[$iTags['title'][$i]]['value']);
					$array[$name]['author'] = plxUtils::getValue($values[$iTags['author'][$i]]['value']);
					$array[$name]['version'] = plxUtils::getValue($values[$iTags['version'][$i]]['value']);
					$array[$name]['date'] = plxUtils::getValue($values[$iTags['date'][$i]]['value']);
					$array[$name]['site'] = plxUtils::getValue($values[$iTags['site'][$i]]['value']);
					$array[$name]['description'] = plxUtils::getValue($values[$iTags['description'][$i]]['value']);
					$array[$name]['file'] = plxUtils::getValue($values[$iTags['file'][$i]]['value']);
				}
			}
		}
		return $array;
	}

	/**
	 * Méthode qui traite le formulaire de téléchargement
	 *
	 * @return	stdio
	 * @author	Stephane F
	 **/
    public function AdminPrepend() {

		if(isset($_POST['download']) AND !empty($_POST['url'])) {

			$remoteFile = $_POST['url'];
			$destination = PLX_PLUGINS.basename($remoteFile);

			# on reteste que l'extension cURL est dispo
			if(!plxMyPluginDownloader::is_cURL()) {
				plxMsg::Error($this->getLang('L_ERR_CURL_NOT_AVAILABLE'));
				header('Location: parametres_plugins.php');
				exit;
			}
			# on teste si le fichier distant est dispo
			if(!plxMyPluginDownloader::is_RemoteFileExists($remoteFile)) {
				plxMsg::Error($this->getLang('L_ERR_REMOTE_FILE'));
				header('Location: parametres_plugins.php');
				exit;
			}
			# téléchargement du fichier distant
			if(!plxMyPluginDownloader::downloadRemoteFile($remoteFile, $destination)) {
				plxMsg::Error($this->getLang('L_ERR_DOWNLOAD'));
				header('Location: parametres_plugins.php');
				exit;
			}

			# dezippage de l'archive
			require_once(PLX_PLUGINS."plxMyPluginDownloader/dUnzip2.inc.php");
			$zip = new dUnzip2($destination); // New Class : arg = fichier à dézipper
			$zip->unzipAll(PLX_PLUGINS); // Unzip All  : arg = dossier de destination

			# redirection
			plxMsg::Info($this->getLang('L_INSTALL_OK'));
			header('Location: parametres_plugins.php');
			exit;
		}

	}

	/**
	 * Méthode qui affiche le formulaire de téléchargement
	 *
	 * @return	stdio
	 * @author	Stephane F
	 **/
    public function AdminSettingsPluginsTop() {?>

<div style="margin:15px 0 15px 0; padding:10px 0 10px 5px; background-color:#efefef">
<form action="parametres_plugins.php" method="post" id="form_MyPluginDownloader">
	<p><?php echo plxToken::getTokenPostMethod() ?></p>
	<p>
		<?php $this->lang('L_URL') ?> : <input type="text" name="url" value="" maxlength="255" size="80"/>&nbsp;
		<input class="button" type="submit" name="download" value="<?php $this->lang('L_DOWNLOAD') ?> " />&nbsp;<?php $this->lang('L_EXTENSION') ?>
	</p>
</form>
</div>

	<?php
	}

	/**
	 * Méthode qui effectue les controles pour le fonctionnement du plugin
	 *
	 * @return	stdio
	 * @author	Stephane F
	 **/
	public function AdminTopBottom() {

		$string = '

			$test1 = plxMyPluginDownloader::is_cURL();
			$test2 = is_dir(PLX_PLUGINS);
			$test3 = is_writable(PLX_PLUGINS);

			if(!$test1 OR !$test2 OR !$test3) {

				echo "<p class=\"warning\">Plugin MyPluginDownloader<br />";
				if(!$test1) echo "<br />'.$this->getLang('L_ERR_CURL').'";
				if(!$test2) echo "<br />'.$this->getLang('L_ERR_PLX_PLUGINS').'";
				if($test2 AND !$test3) echo "<br />'.$this->getLang('L_ERR_WRITE_ACCESS').'";
				echo "</p>";
				plxMsg::Display();

			}
		';
		echo '<?php '.$string.' ?>';

	}


}
?>