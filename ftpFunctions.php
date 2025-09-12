<?php

define('FTP_SERVER', 'projeto.vetel.ind.br');
define('LOGIN', 'u447438965.projeto');
define('PASSWORD', '7BaBS5SvYd@');

function uploadFile($fileName, $filePath)
{
	$connId = ftp_connect(FTP_SERVER) or die('ERROR_CONNECTION');
	$serverFile = "/img/logo/$fileName";

	if (!ftp_login($connId, LOGIN, PASSWORD)) {
		die('ERROR_LOGIN');
	}

	$ret = ftp_put($connId, $serverFile, $filePath, FTP_BINARY);
	ftp_close($connId);

	if ($ret != FTP_FINISHED) {
		return false;
	}

	return $serverFile;
}
