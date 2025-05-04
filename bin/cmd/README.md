
--------------------------------------------------------------------------------------------------------------------------------------------

 commands
 --------
  - app-history.php         RosatraderHistoryCommand
     status                                                 Show Rosatrader history status.
     synchronize                                            Synchronize history status in the db with history stored in the file system.
     update                                                 Update the stored history (connects to Dukascopy).
  - duk-status.php          DukascopyHistoryStartCommand    Display/update local or remote start times of the Dukascopy history.
  - mt4-history.php         MetaTraderHistoryCommand        Creates MT4 history files ".hst" for all timeframes of a symbol.
  - mt4-scaleHistory.php    ScaleHistoryCommand             Transforms (add, substract, multiply, divide) prices of an MT4 history.
  - mt4-symbols.php         MetaTraderSymbolsCommand        Split MT4 "symbols.raw" into a file per symbol.

--------------------------------------------------------------------------------------------------------------------------------------------

legacy
 ------
  - app-generatePLSeries.php        Generates a PnL timeseries of the specified test.
  - app-updateSyntheticBars.php     Updates M1 history of synthetic symbols.
  - duk-updateTicks.php             Updates local Dukascopy tickdata (stored in Rosatrader format).
  - mt4-createTickfile.php          Creates an FXT tick file for the tester.
  - mt4-dir.php                     Lists meta infos of the MT4 history ".hst" in a directory.
  - mt4-findOffset.php              Finds the byte offset of the next bar after the specified time in an MT4 history file ".hst".
  - mt4-importTest.php              Imports test results into the Rosatrader test database.
  - mt4-listSymbols.php             Lists meta infos of the symbols contained in an MT4 "symbols.raw" file.
  - mt4-updateHistory.php           Updates MT4 history files ".hst" with data from the Rosatrader history.

--------------------------------------------------------------------------------------------------------------------------------------------
