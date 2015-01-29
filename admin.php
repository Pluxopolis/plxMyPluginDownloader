<?php if(!defined('PLX_ROOT')) exit; ?>

<?php

# dossier local pour le cache
$cache_dir = PLX_PLUGINS.'cache/';

# infos sur le repository
$repository_url = 'https://raw.githubusercontent.com/Pluxopolis/repository/master/'; # avec un slash à la fin
$repository_xmlfile = 'repository.xml';
$repository_version = 'repository.version';

# Control du token du formulaire
plxToken::validateFormToken($_POST);

# vérification de la présence du dossier cache
if(!is_dir(PLX_PLUGINS.'/cache')) {
	mkdir(PLX_PLUGINS.'/cache',0755,true);
}

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
		$zipfile = PLX_PLUGINS.$plugName.'.zip';

		if(!plxMyPluginDownloader::downloadRemoteFile($repo[$plugName]['file'], $zipfile)) {
			plxMsg::Error($plxPlugin->getLang('L_ERR_DOWNLOAD'));
			header('Location: plugin.php?p=plxMyPluginDownloader');
			exit;
		}
		
		# renommer le dossier du plugin si déja présent
		if (file_exists(PLX_PLUGINS.$plugName)) {
			$tempPlugnamePN = PLX_PLUGINS.$plugName.date("YmdHis");
			rename(PLX_PLUGINS.$plugName, $tempPlugnamePN);
		}

		# dezippage de l'archive
		require_once(PLX_PLUGINS."plxMyPluginDownloader/dUnzip2.inc.php");
		$zip = new dUnzip2($zipfile); // New Class : arg = fichier à dézipper
		$zip->unzipAll(PLX_PLUGINS, "", true, 0755); // Unzip All  : args = dossier de destination

		# on renomme le dossier extrait
		rename(PLX_PLUGINS.$plugName.'-'.str_replace('.zip', '', basename($repo[$plugName]['file'])), PLX_PLUGINS.$plugName);

		# on supprimer le fichier .zip
		unlink($zipfile);

		# on teste si le dézippage semble ok par la présence du fichier infos.xml du plugin
		if(!is_file(PLX_PLUGINS.$plugName.'/infos.xml')) {
			# remettre en place le dossier sauvegardé
			if (file_exists($tempPlugnamePN)) {
				rename($tempPlugnamePN, PLX_PLUGINS.$plugName);
			}
			
			# afficher qu'une erreur s'est produite
			plxMsg::Error($plxPlugin->getLang('L_ERR_INSTALL'));
		}
		else {
			# supprimer la sauvegarde de l'ancien dossier
			if (file_exists($tempPlugnamePN)) {
				plxMyPluginDownloader::deleteDir($tempPlugnamePN);
			}
			
			# afficher que tout est ok
			plxMsg::Info($plxPlugin->getLang('L_INSTALL_OK'));
		}

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

		echo '<tr>';
		echo '<td class="icon"><img src="'.$plug['icon'].'" alt="" /></td>';
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