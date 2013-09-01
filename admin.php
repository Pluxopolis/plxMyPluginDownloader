<?php if(!defined('PLX_ROOT')) exit; ?>

<?php

# dossier local pour le cache
$cache_dir = PLX_PLUGINS.'plxMyPluginDownloader/cache/';

# infos sur le repository
$repository_url = 'http://repository.my-pluxml.googlecode.com/git/'; # avec un slash à la fin
$repository_xmlfile = 'repository.xml';
$repository_version = 'repository.version';

# Control du token du formulaire
plxToken::validateFormToken($_POST);

if(!empty($_POST)) {

	foreach($_POST['button'] as $plugName => $dummy) {

		# on reteste que l'extension cURL est dispo
		if(!plxMyPluginDownloader::is_cURL()) {
			plxMsg::Error($this->getLang('L_ERR_CURL_NOT_AVAILABLE'));
			header('Location: parametres_plugins.php');
			exit;
		}

		# récuperation des infos sur le fichier du repository mis en cache
		if(!$repo = plxMyPluginDownloader::getRepository($cache_dir.$repository_xmlfile)) {
			plxMsg::Error($plxPlugin->getLang('L_ERR_CACHE'));
			header('Location: plugin.php?p=plxMyPluginDownloader');
			exit;
		}

		# on teste si le fichier distant est dispo
		if(!plxMyPluginDownloader::is_RemoteFileExists($repo[$plugName]['file'])) {
			plxMsg::Error($plxPlugin->getLang('L_ERR_REMOTE_FILE'));
			header('Location: plugin.php?p=plxMyPluginDownloader');
			exit;
		}
		# téléchargement du fichier distant
		if(!plxMyPluginDownloader::downloadRemoteFile($repo[$plugName]['file'], PLX_PLUGINS.basename($repo[$plugName]['file']))) {
			plxMsg::Error($plxPlugin->getLang('L_ERR_DOWNLOAD'));
			header('Location: plugin.php?p=plxMyPluginDownloader');
			exit;
		}

		# dezippage de l'archive
		require_once(PLX_PLUGINS."plxMyPluginDownloader/dUnzip2.inc.php");
		$zip = new dUnzip2(PLX_PLUGINS.basename($repo[$plugName]['file'])); // New Class : arg = fichier à dézipper
		$zip->unzipAll(PLX_PLUGINS); // Unzip All  : arg = dossier de destination

		# on teste si le dézippage semble ok par la présence du fichier infos.xml du plugin
		if(!is_file(PLX_PLUGINS.$plugName.'/infos.xml'))
			plxMsg::Error($plxPlugin->getLang('L_ERR_INSTALL'));
		else
			plxMsg::Info($plxPlugin->getLang('L_INSTALL_OK'));

		# Redirection
		header('Location: plugin.php?p=plxMyPluginDownloader');
		exit;
	}
}

# recupération du n° de version du repository distant
if(!$remote_version = plxMyPluginDownloader::getRemoteFileContent($repository_url.$repository_version))
	echo $plxPlugin->getLang('L_ERR_REPOSITORY');
else { # traitement dépot

# recupération du n° de version du repository mis en cache
$version = '';
if(is_file($cache_dir.$repository_version))
	$version = file_get_contents($cache_dir.$repository_version);
plxUtils::write($remote_version, $cache_dir.$repository_version);

# on récupère le fichier distant repository.xlm s'il n'existe pas en cache ou si nouveau n° de version
if($version=='' OR $version!=$remote_version or !is_file($cache_dir.$repository_xmlfile)) {
	plxMyPluginDownloader::downloadRemoteFile($repository_url.$repository_xmlfile, $cache_dir.$repository_xmlfile);
}

# on récupère la liste des plugins dans le dossier plugins
$aPlugins = array();
$dirs = plxGlob::getInstance(PLX_PLUGINS, true);
if(sizeof($dirs->aFiles)>0) {
	foreach($dirs->aFiles as $plugName) {
		if(!isset($aPlugins[$plugName]) AND $plugInstance=$plxAdmin->plxPlugins->getInstance($plugName)) {
			$plugInstance->getInfos();
			$aPlugins[$plugName] = $plugInstance;
		}
	}
}

echo '<p>'.L_MENU_CONFIG.' > <a href="parametres_plugins.php" title="'.L_MENU_CONFIG_PLUGINS_TITLE.'">'.L_MENU_CONFIG_PLUGINS.'</a></p>';

?>

<form action="plugin.php?p=plxMyPluginDownloader" method="post" id="form_plugindownloader">
<p><?php echo plxToken::getTokenPostMethod() ?></p>
<table class="mypdler" cellspacing="0">
<?php

# lecture du fichier xml contenant les infos sur les plugins dispo dans le repository
if($repo = plxMyPluginDownloader::getRepository($cache_dir.$repository_xmlfile)) {

	foreach($repo as $plugName => $plug) {

		$update=false;
		if(isset($aPlugins[$plugName]))
			$update = version_compare($aPlugins[$plugName]->getInfo('version'), $plug['version'], "<");

		$new = !is_dir(PLX_PLUGINS.$plugName);

		$color='';
		if($update) $color = ' new-red';

		# determination de l'icone à afficher
		if(plxMyPluginDownloader::is_RemoteFileExists($repository_url.$plugName.'/icon.png'))
			$icon=$repository_url.$plugName.'/icon.png';
		elseif(plxMyPluginDownloader::is_RemoteFileExists($repository_url.$plugName.'/icon.jpg'))
			$icon=$repository_url.$plugName.'/icon.jpg';
		elseif(plxMyPluginDownloader::is_RemoteFileExists($repository_url.$plugName.'/icon.gif'))
			$icon=$repository_url.$plugName.'/icon.gif';
		else
			$icon=PLX_CORE.'admin/theme/images/icon_plugin.png';

		echo '<tr>';
		echo '<td class="icon"><img src="'.$icon.'" alt="'.plxUtils::strCheck($plug['title']).'" /></td>';
		echo '<td class="description'.$color.'">';
			echo '<strong>'.plxUtils::strCheck($plug['title']).'</strong>';
			echo ' - '.$plxPlugin->getLang('L_VERSION').' <strong>'.plxUtils::strCheck($plug['version']).'</strong>';
			if($plug['date']!='')
				echo ' ('.plxUtils::strCheck($plug['date']).')';
			echo '<br />';
			echo plxUtils::strCheck($plug['description']).'<br />';
			echo $plxPlugin->getLang('L_AUTHOR').' : '.plxUtils::strCheck($plug['author']);
			if($plug['site']!='')
				echo ' - <a href="'.plxUtils::strCheck($plug['site']).'">'.plxUtils::strCheck($plug['site']).'</a>';
		echo '</td>';

		if($update)
			echo '<td class="action"><input type="submit" class="btnUpdate" name="button['.$plugName.']" value="'.$plxPlugin->getLang('L_UPDATE').'" /></td>';
		elseif($new)
			echo '<td class="action"><input type="submit" class="btnDownload" name="button['.$plugName.']" value="'.$plxPlugin->getLang('L_DOWNLOAD').'" /></td>';
		else
			echo '<td class="action"><input type="submit" name="button['.$plugName.']" value="'.$plxPlugin->getLang('L_DOWNLOAD').'" /></td>';

		echo "</tr>";
	}
}
else {
	echo '<tr><td>Aucun plugin</td></tr>';
}
?>
</table>
</form>
<?php } # fin traitement dépot ?>