<?php
// Klassendefinitionen
// -------------------
$dir = dirName(__FILE__).DIRECTORY_SEPARATOR;

$__classes['DownloadFTPConfigurationAction'    ] = $dir.'actions/DownloadFTPConfigurationAction';
$__classes['DownloadFTPConfigurationActionForm'] = $dir.'actions/DownloadFTPConfigurationActionForm';
$__classes['UploadAccountHistoryAction'        ] = $dir.'actions/UploadAccountHistoryAction';
$__classes['UploadAccountHistoryActionForm'    ] = $dir.'actions/UploadAccountHistoryActionForm';
$__classes['UploadFTPConfigurationAction'      ] = $dir.'actions/UploadFTPConfigurationAction';
$__classes['UploadFTPConfigurationActionForm'  ] = $dir.'actions/UploadFTPConfigurationActionForm';

$__classes['ImportHelper'                      ] = $dir.'helper/ImportHelper';
$__classes['MT4Helper'                         ] = $dir.'helper/MT4Helper';
$__classes['Validator'                         ] = $dir.'helper/Validator';
$__classes['ViewHelper'                        ] = $dir.'helper/ViewHelper';

$__classes['Account'                           ] = $dir.'model/Account';
$__classes['AccountDAO'                        ] = $dir.'model/AccountDAO';

unset($dir);
?>
