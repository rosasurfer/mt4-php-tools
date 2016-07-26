<?php
/**
 * Class map for class loader (fastest way to load classes)
 */
return array(
   'DownloadFTPConfigurationAction'     => APPLICATION_ROOT.'/src/WEB-INF/classes/actions/DownloadFTPConfigurationAction.php',
   'DownloadFTPConfigurationActionForm' => APPLICATION_ROOT.'/src/WEB-INF/classes/actions/DownloadFTPConfigurationActionForm.php',
   'UploadAccountHistoryAction'         => APPLICATION_ROOT.'/src/WEB-INF/classes/actions/UploadAccountHistoryAction.php',
   'UploadAccountHistoryActionForm'     => APPLICATION_ROOT.'/src/WEB-INF/classes/actions/UploadAccountHistoryActionForm.php',
   'UploadFTPConfigurationAction'       => APPLICATION_ROOT.'/src/WEB-INF/classes/actions/UploadFTPConfigurationAction.php',
   'UploadFTPConfigurationActionForm'   => APPLICATION_ROOT.'/src/WEB-INF/classes/actions/UploadFTPConfigurationActionForm.php',

   'Dukascopy'                          => APPLICATION_ROOT.'/src/WEB-INF/classes/dukascopy/Dukascopy.php',
   'DukascopyException'                 => APPLICATION_ROOT.'/src/WEB-INF/classes/dukascopy/DukascopyException.php',

   'HistoryFile'                        => APPLICATION_ROOT.'/src/WEB-INF/classes/metatrader/HistoryFile.php',
   'HistoryHeader'                      => APPLICATION_ROOT.'/src/WEB-INF/classes/metatrader/HistoryHeader.php',
   'HistorySet'                         => APPLICATION_ROOT.'/src/WEB-INF/classes/metatrader/HistorySet.php',
   'MetaTraderException'                => APPLICATION_ROOT.'/src/WEB-INF/classes/metatrader/MetaTraderException.php',
   'MT4'                                => APPLICATION_ROOT.'/src/WEB-INF/classes/metatrader/MT4.php',

   'MyFX'                               => APPLICATION_ROOT.'/src/WEB-INF/classes/myfx/MyFX.php',

   'DataNotFoundException'              => APPLICATION_ROOT.'/src/WEB-INF/classes/simpletrader/DataNotFoundException.php',
   'SimpleTrader'                       => APPLICATION_ROOT.'/src/WEB-INF/classes/simpletrader/SimpleTrader.php',

   'LZMA'                               => APPLICATION_ROOT.'/src/WEB-INF/classes/helper/LZMA.php',
   'ImportHelper'                       => APPLICATION_ROOT.'/src/WEB-INF/classes/helper/ImportHelper.php',
   'ReportHelper'                       => APPLICATION_ROOT.'/src/WEB-INF/classes/helper/ReportHelper.php',
   'Validator'                          => APPLICATION_ROOT.'/src/WEB-INF/classes/helper/Validator.php',
   'ViewHelper'                         => APPLICATION_ROOT.'/src/WEB-INF/classes/helper/ViewHelper.php',

   'Account'                            => APPLICATION_ROOT.'/src/WEB-INF/classes/model/Account.php',
   'AccountDAO'                         => APPLICATION_ROOT.'/src/WEB-INF/classes/model/AccountDAO.php',
   'ClosedPosition'                     => APPLICATION_ROOT.'/src/WEB-INF/classes/model/ClosedPosition.php',
   'ClosedPositionDAO'                  => APPLICATION_ROOT.'/src/WEB-INF/classes/model/ClosedPositionDAO.php',
   'OpenPosition'                       => APPLICATION_ROOT.'/src/WEB-INF/classes/model/OpenPosition.php',
   'OpenPositionDAO'                    => APPLICATION_ROOT.'/src/WEB-INF/classes/model/OpenPositionDAO.php',
   'Signal'                             => APPLICATION_ROOT.'/src/WEB-INF/classes/model/Signal.php',
   'SignalDAO'                          => APPLICATION_ROOT.'/src/WEB-INF/classes/model/SignalDAO.php',
);
