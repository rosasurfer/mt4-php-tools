<?php
/**
 * Class map for class loader (fastest way to load classes)
 */
return array(
   'DownloadFTPConfigurationAction'                => APPLICATION_ROOT.'/app/controller/actions/DownloadFTPConfigurationAction.php',
   'UploadAccountHistoryAction'                    => APPLICATION_ROOT.'/app/controller/actions/UploadAccountHistoryAction.php',
   'UploadFTPConfigurationAction'                  => APPLICATION_ROOT.'/app/controller/actions/UploadFTPConfigurationAction.php',

   'DownloadFTPConfigurationActionForm'            => APPLICATION_ROOT.'/app/controller/forms/DownloadFTPConfigurationActionForm.php',
   'UploadAccountHistoryActionForm'                => APPLICATION_ROOT.'/app/controller/forms/UploadAccountHistoryActionForm.php',
   'UploadFTPConfigurationActionForm'              => APPLICATION_ROOT.'/app/controller/forms/UploadFTPConfigurationActionForm.php',

   'ImportHelper'                                  => APPLICATION_ROOT.'/app/lib/ImportHelper.php',
   'LZMA'                                          => APPLICATION_ROOT.'/app/lib/LZMA.php',
   'ReportHelper'                                  => APPLICATION_ROOT.'/app/lib/ReportHelper.php',
   'Validator'                                     => APPLICATION_ROOT.'/app/lib/Validator.php',
   'ViewHelper'                                    => APPLICATION_ROOT.'/app/lib/ViewHelper.php',

   'Dukascopy'                                     => APPLICATION_ROOT.'/app/lib/dukascopy/Dukascopy.php',
   'DukascopyException'                            => APPLICATION_ROOT.'/app/lib/dukascopy/DukascopyException.php',

   'HistoryFile'                                   => APPLICATION_ROOT.'/app/lib/metatrader/HistoryFile.php',
   'HistoryHeader'                                 => APPLICATION_ROOT.'/app/lib/metatrader/HistoryHeader.php',
   'HistorySet'                                    => APPLICATION_ROOT.'/app/lib/metatrader/HistorySet.php',
   'MetaTraderException'                           => APPLICATION_ROOT.'/app/lib/metatrader/MetaTraderException.php',
   'MT4'                                           => APPLICATION_ROOT.'/app/lib/metatrader/MT4.php',

   'MyFX'                                          => APPLICATION_ROOT.'/app/lib/myfx/MyFX.php',

   'rosasurfer\myfx\lib\myfxbook\MyfxBook'         => APPLICATION_ROOT.'/app/lib/myfxbook/MyfxBook.php',

   'DataNotFoundException'                         => APPLICATION_ROOT.'/app/lib/simpletrader/DataNotFoundException.php',
   'rosasurfer\myfx\lib\simpletrader\SimpleTrader' => APPLICATION_ROOT.'/app/lib/simpletrader/SimpleTrader.php',

   'Account'                                       => APPLICATION_ROOT.'/app/model/Account.php',
   'AccountDAO'                                    => APPLICATION_ROOT.'/app/model/AccountDAO.php',
   'ClosedPosition'                                => APPLICATION_ROOT.'/app/model/ClosedPosition.php',
   'ClosedPositionDAO'                             => APPLICATION_ROOT.'/app/model/ClosedPositionDAO.php',
   'OpenPosition'                                  => APPLICATION_ROOT.'/app/model/OpenPosition.php',
   'OpenPositionDAO'                               => APPLICATION_ROOT.'/app/model/OpenPositionDAO.php',
   'Signal'                                        => APPLICATION_ROOT.'/app/model/Signal.php',
   'SignalDAO'                                     => APPLICATION_ROOT.'/app/model/SignalDAO.php',
);
