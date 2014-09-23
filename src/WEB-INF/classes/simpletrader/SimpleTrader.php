<?
/**
 * www.simpletrader.net related functionality
 */
class SimpleTrader extends StaticClass {


   /**
    * Parst eine simpletrader.net HTML-Seite mit Signaldaten.
    *
    * @param  string $signal     - Signal
    * @param  string $html       - Inhalt der HTML-Seite
    * @param  array  $openTrades - Array zur Aufnahme der offenen Positionen
    * @param  array  $history    - Array zur Aufnahme der Signalhistory
    */
   public static function parseSignalData($signal, &$html, array &$openTrades, array &$history) {
      if (!is_string($signal)) throw new IllegalTypeException('Illegal type of parameter $signal: '.getType($signal));
      if (!is_string($html))   throw new IllegalTypeException('Illegal type of parameter $html: '.getType($html));

      // HTML-Entities konvertieren
      $html = str_replace('&nbsp;', ' ', $html);

      // ggf. RegExp-Stringlimit erhöhen
      if (strLen($html) > (int)ini_get('pcre.backtrack_limit'))
         ini_set('pcre.backtrack_limit', strLen($html));

      $matchedTables = $openTradeRows = $historyRows = $matchedOpenTrades = $matchedHistoryEntries = 0;
      $tables = array();


      // Tabellen <table id="openTrades"> und <table id="history"> extrahieren
      $matchedTables = preg_match_all('/<table\b.*\bid="(opentrades|history)".*>.*<tbody\b.*>(.*)<\/tbody>.*<\/table>/isU', $html, $tables, PREG_SET_ORDER);
      foreach ($tables as $i => &$table) {
         $table[0] = 'table '.($i+1);
         $table[1] = strToLower($table[1]);

         // offene Positionen extrahieren und parsen (Timezone: GMT)
         if ($table[1] == 'opentrades') {
            /*                                                                   // Array([ 0:          ] => {matched html}
            <tr class="red topDir" title="Take Profit: 1.730990 Stop Loss: -">   //       [ 1:TakeProfit] => 1.319590
               <td class="center">2014/09/08 13:25:42</td>                       //       [ 2:StopLoss  ] => -
               <td class="center">1.294130</td>                                  //       [ 3:OpenTime  ] => 2014/09/04 08:15:12
               <td class="center">1.24</td>                                      //       [ 4:OpenPrice ] => 1.314590
               <td class="center">Sell</td>                                      //       [ 5:Lots      ] => 0.16
               <td class="center">EURUSD</td>                                    //       [ 6:Type      ] => Buy
               <td class="center">-32.57</td>                                    //       [ 7:Symbol    ] => EURUSD
               <td class="center">-1.8</td>                                      //       [ 8:Profit    ] => -281.42
               <td class="center">1999552</td>                                   //       [ 9:Pips      ] => -226.9
            </tr>                                                                //       [10:Comment   ] => 2000641)
            */
            $openTradeRows     = preg_match_all('/<tr\b/is', $table[2], $openTrades);
            $matchedOpenTrades = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $openTrades, PREG_SET_ORDER);
            foreach ($openTrades as $i => &$row) {
               if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2]))) throw new plRuntimeException('Error parsing TakeProfit or StopLoss in open position row '.($i+1).":\n".$row[0]);

               // 0:
               //$row[0] = 'row '.($i+1);

               // 1:TakeProfit
               $sValue = trim($row[I_STOP_TAKEPROFIT]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid TakeProfit found in open position row '.($i+1).': "'.$row[I_STOP_TAKEPROFIT].'"');
               $row['takeprofit'] = $dValue;

               // 2:StopLoss
               $sValue = trim($row[I_STOP_STOPLOSS]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid StopLoss found in open position row '.($i+1).': "'.$row[I_STOP_STOPLOSS].'"');
               $row['stoploss'] = $dValue;

               // 3:OpenTime
               $sOpenTime = trim($row[I_STOP_OPENTIME]);
               if (!($time=strToTime($sOpenTime.' GMT'))) throw new plRuntimeException('Invalid OpenTime found in open position row '.($i+1).': "'.$row[I_STOP_OPENTIME].'"');
             //$row[I_STOP_OPENTIME] = date(DATE_RFC822, $time);
               $row['opentime' ] = $time;
               $row['closetime'] = null;

               // 4:OpenPrice
               $sValue = trim($row[I_STOP_OPENPRICE]);
               if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid OpenPrice found in open position row '.($i+1).': "'.$row[I_STOP_OPENPRICE].'"');
               $row['openprice' ] = $dValue;
               $row['closeprice'] = null;

               // 5:LotSize
               $sValue = trim($row[I_STOP_LOTSIZE]);
               if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid LotSize found in open position row '.($i+1).': "'.$row[I_STOP_LOTSIZE].'"');
               $row['lots'] = $dValue;

               // 6:Type
               $sValue = trim(strToLower($row[I_STOP_TYPE]));
               if ($sValue!='buy' && $sValue!='sell') throw new plRuntimeException('Invalid OperationType found in open position row '.($i+1).': "'.$row[I_STOP_TYPE].'"');
               $row['type'] = $sValue;

               // 7:Symbol
               $sValue = trim($row[I_STOP_SYMBOL]);
               if (empty($sValue)) throw new plRuntimeException('Invalid Symbol found in open position row '.($i+1).': "'.$row[I_STOP_SYMBOL].'"');
               $row['symbol'] = $sValue;

               // 8:Profit
               $sValue = trim($row[I_STOP_PROFIT]);
               if (!is_numeric($sValue)) throw new plRuntimeException('Invalid Profit found in open position row '.($i+1).': "'.$row[I_STOP_PROFIT].'"');
               $row['profit'    ] = (float)$sValue;
               $row['commission'] = 0;
               $row['swap'      ] = 0;

               // 9:Pips

               // 10:Comment
               $sValue = trim($row[I_STOP_COMMENT]);
               if (!ctype_digit($sValue)) throw new plRuntimeException('Invalid Comment found in open position row '.($i+1).': "'.$row[I_STOP_COMMENT].'" (non-digits)');
               $row['ticket'] = (int)$sValue;

               unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10]);
            }
            // offene Positionen sortieren
            uSort($openTrades, array(__CLASS__, 'compareTradesByOpenTimeTicket'));
         }

         // History extrahieren und parsen; TP und SL werden, falls angegeben, erkannt (Timezone: GMT)
         if ($table[1] == 'history') {
            /*                                                                   // Array([ 0:          ] => {matched html}
            <tr class="green">                                                   //       [ 1:TakeProfit] =>
               <td class="center">2014/09/05 16:32:52</td>                       //       [ 2:StopLoss  ] =>
               <td class="center">2014/09/08 04:57:25</td>                       //       [ 3:OpenTime  ] => 2014/09/09 13:05:58
               <td class="center">1.294620</td>                                  //       [ 4:CloseTime ] => 2014/09/09 13:08:15
               <td class="center">1.294130</td>                                  //       [ 5:OpenPrice ] => 1.742870
               <td class="center">1.24</td>                                      //       [ 6:ClosePrice] => 1.743470
               <td class="center">Sell</td>                                      //       [ 7:Lots      ] => 0.12
               <td class="center">EURUSD</td>                                    //       [ 8:Type      ] => Sell
               <td class="center">47.43</td>                                     //       [ 9:Symbol    ] => GBPAUD
               <td class="center">4.9</td>                                       //       [10:Profit    ] => -7.84
               <td class="center">1996607</td>                                   //       [11:Pips      ] => -6
            </tr>                                                                //       [12:Comment   ] => 2002156)
            */
            $historyRows           = preg_match_all('/<tr\b/is', $table[2], $history);
            $matchedHistoryEntries = preg_match_all('/<tr\b[^>]*?(?:"\s*Take\s*Profit:\s*([0-9.-]+)\s*Stop\s*Loss:\s*([0-9.-]+)\s*")?\s*>(?U)\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>\s*<td\b.*>(.*)<\/td>/is', $table[2], $history, PREG_SET_ORDER);
            foreach ($history as $i => &$row) {
               if (is_int(strPos($row[0], 'Take Profit')) && (empty($row[1]) || empty($row[2]))) throw new plRuntimeException('Error parsing TakeProfit or StopLoss in history row '.($i+1).":\n".$row[0]);

               // 0:
               //$row[0] = 'row '.($i+1);

               // 1:TakeProfit
               $sValue = trim($row[I_STH_TAKEPROFIT]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid TakeProfit found in history row '.($i+1).': "'.$row[I_STH_TAKEPROFIT].'"');
               $row['takeprofit'] = $dValue;

               // 2:StopLoss
               $sValue = trim($row[I_STH_STOPLOSS]);
               if (empty($sValue) || $sValue=='-') $dValue = null;
               else if (($dValue=(float)$sValue) <= 0 || !is_numeric($sValue)) throw new plRuntimeException('Invalid StopLoss found in history row '.($i+1).': "'.$row[I_STH_STOPLOSS].'"');
               $row['stoploss'] = $dValue;

               // 3:OpenTime
               $sOpenTime = trim($row[I_STH_OPENTIME]);
               if (!($time=strToTime($sOpenTime.' GMT'))) throw new plRuntimeException('Invalid OpenTime found in history row '.($i+1).': "'.$row[I_STH_OPENTIME].'"');
               $row['opentime'] = $time;

               // 4:CloseTime
               $sCloseTime = trim($row[I_STH_CLOSETIME]);
               if (!($time=strToTime($sCloseTime.' GMT'))) throw new plRuntimeException('Invalid CloseTime found in history row '.($i+1).': "'.$row[I_STH_CLOSETIME].'"');
               if ($row['opentime'] > $time) {
                  // bekannte Fehler selbständig fixen
                  $sTicket = trim($row[I_STH_COMMENT]);
                  if      ($signal=='smarttrader' && $sTicket=='1175928') $row['opentime'] = $time;
                  else if ($signal=='caesar21'    && $sTicket=='1897240') $row['opentime'] = $time;
                  else if ($signal=='caesar21'    && $sTicket=='1803494') $row['opentime'] = $time;
                  else if ($signal=='caesar21'    && $sTicket=='1803493') $row['opentime'] = $time;
                  else if ($signal=='caesar21'    && $sTicket=='1680703') $row['opentime'] = $time;
                  else if ($signal=='caesar21'    && $sTicket=='1617317') $row['opentime'] = $time;
                  else if ($signal=='caesar21'    && $sTicket=='1602520') $row['opentime'] = $time;
                  else throw new plRuntimeException('Invalid Open-/CloseTime pair found in history #'.$sTicket.': '.$sOpenTime.'" / "'.$sCloseTime.'"');
               }
               $row['closetime'] = $time;

               // 5:OpenPrice
               $sValue = trim($row[I_STH_OPENPRICE]);
               if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid OpenPrice found in history row '.($i+1).': "'.$row[I_STH_OPENPRICE].'"');
               $row['openprice'] = $dValue;

               // 6:ClosePrice
               $sValue = trim($row[I_STH_CLOSEPRICE]);
               if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid ClosePrice found in history row '.($i+1).': "'.$row[I_STH_CLOSEPRICE].'"');
               $row['closeprice'] = $dValue;

               // 7:LotSize
               $sValue = trim($row[I_STH_LOTSIZE]);
               if (!is_numeric($sValue) || ($dValue=(float)$sValue) <= 0) throw new plRuntimeException('Invalid LotSize found in history row '.($i+1).': "'.$row[I_STH_LOTSIZE].'"');
               $row['lots'] = $dValue;

               // 8:Type
               $sValue = trim(strToLower($row[I_STH_TYPE]));
               if ($sValue!='buy' && $sValue!='sell') throw new plRuntimeException('Invalid OperationType found in history row '.($i+1).': "'.$row[I_STH_TYPE].'"');
               $row['type'] = $sValue;

               // 9:Symbol
               $sValue = trim($row[I_STH_SYMBOL]);
               if (empty($sValue)) throw new plRuntimeException('Invalid Symbol found in history row '.($i+1).': "'.$row[I_STH_SYMBOL].'"');
               $row['symbol'] = $sValue;

               // 10:Profit
               $sValue = trim($row[I_STH_PROFIT]);
               if (!is_numeric($sValue)) throw new plRuntimeException('Invalid Profit found in history row '.($i+1).': "'.$row[I_STH_PROFIT].'"');
               $row['profit'    ] = (float)$sValue;
               $row['commission'] = 0;
               $row['swap'      ] = 0;

               // 11:Pips

               // 12:Comment
               $sValue = trim($row[I_STH_COMMENT]);
               if (!ctype_digit($sValue)) throw new plRuntimeException('Invalid Comment found in history row '.($i+1).': "'.$row[I_STH_COMMENT].'" (non-digits)');
               $row['ticket'] = (int)$sValue;

               unset($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $row[8], $row[9], $row[10], $row[11], $row[12]);
            }
            // History sortieren
            uSort($history, array(__CLASS__, 'compareTradesByCloseTimeOpenTimeTicket'));
         }
      }

      if ($openTradeRows != $matchedOpenTrades    ) throw new plRuntimeException('Could not match '.($openTradeRows-$matchedOpenTrades  ).' row'.($openTradeRows-$matchedOpenTrades  ==1 ? '':'s'));
      if ($historyRows   != $matchedHistoryEntries) throw new plRuntimeException('Could not match '.($historyRows-$matchedHistoryEntries).' row'.($historyRows-$matchedHistoryEntries==1 ? '':'s'));
   }


   /**
    * Comparator, der zwei Trades zunächst anhand ihrer OpenTime vergleicht. Ist die OpenTime gleich,
    * werden die Trades anhand ihres Tickets verglichen.
    *
    * @param  array $tradeA
    * @param  array $tradeB
    *
    * @return int - positiver Wert, wenn $tradeA nach $tradeB geöffnet wurde;
    *               negativer Wert, wenn $tradeA vor $tradeB geöffnet wurde;
    *               0, wenn beide Trades zum selben Zeitpunkt geöffnet wurden
    */
   private static function compareTradesByOpenTimeTicket(array &$tradeA, array &$tradeB) {
      if ($tradeA['opentime'] > $tradeB['opentime']) return  1;
      if ($tradeA['opentime'] < $tradeB['opentime']) return -1;

      if ($tradeA['ticket'  ] > $tradeB['ticket'  ]) return  1;
      if ($tradeA['ticket'  ] < $tradeB['ticket'  ]) return -1;

      return 0;
   }


   /**
    * Comparator, der zwei Trades zunächst anhand ihrer CloseTime vergleicht. Ist die CloseTime gleich, werden die Trades
    * anhand ihrer OpenTime verglichen. Ist auch die OpenTime gleich, werden die Trades anhand ihres Tickets verglichen.
    *
    * @param  array $tradeA
    * @param  array $tradeB
    *
    * @return int - positiver Wert, wenn $tradeA nach $tradeB geschlossen wurde;
    *               negativer Wert, wenn $tradeA vor $tradeB geschlossen wurde;
    *               0, wenn beide Trades zum selben Zeitpunkt geöffnet und geschlossen wurden
    */
   private static function compareTradesByCloseTimeOpenTimeTicket(array &$tradeA, array &$tradeB) {
      if ($tradeA['closetime'] > $tradeB['closetime']) return  1;
      if ($tradeA['closetime'] < $tradeB['closetime']) return -1;
      return self ::compareTradesByOpenTimeTicket($tradeA, $tradeB);
   }


   /**
    * Handler für PositionOpen-Events eines SimpleTrade-Signals.
    *
    * @param  OpenPosition $position - die geöffnete Position
    */
   public static function onPositionOpen(OpenPosition $position) {
      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = 'position opened: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().'  TP: '.ifNull($position->getTakeProfit(),'-').'  SL: '.ifNull($position->getStopLoss(), '-').'  ('.$position->getOpenTime('H:i:s').')';
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      try {
         $mailMsg = $signal->getName().' Open '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice();
         foreach (MyFX ::getMailSignalReceivers() as $receiver) {
            mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
         }
      }
      catch (Exception $ex) { Logger ::log($ex, L_ERROR, __CLASS__); }


      // Benachrichtigung per SMS
      try {
         $smsMsg = 'Opened '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().(($tp=$position->getTakeProfit()) ? "\nTP: $tp":'').(($sl=$position->getStopLoss()) ? ($tp ? '  ':"\n")."SL: $sl":'')."\n\n#".$position->getTicket().'  ('.$position->getOpenTime('H:i:s').')';
         foreach (MyFX ::getSmsSignalReceivers() as $receiver) {
            MyFX ::sendSms($receiver, $signal, $smsMsg);
         }
      }
      catch (Exception $ex) { Logger ::log($ex, L_ERROR, __CLASS__); }
   }


   /**
    * Handler für PositionModify-Events eines SimpleTrade-Signals.
    *
    * @param  OpenPosition $position - die modifizierte Position (nach der Änderung)
    * @param  float        $prevTP   - der vorherige TakeProfit-Wert
    * @param  float        $prevSL   - der vorherige StopLoss-Wert
    */
   public static function onPositionModify(OpenPosition $position, $prevTP, $prevSL) {
      if (!is_null($prevTP) && !is_float($prevTP)) throw new IllegalTypeException('Illegal type of argument $prevTP: '.getType($prevSL));
      if (!is_null($prevSL) && !is_float($prevSL)) throw new IllegalTypeException('Illegal type of argument $prevSL: '.getType($prevSL));

      $modification = $tpMsg = $slMsg = null;
      if (($current=$position->getTakeprofit()) != $prevTP) $modification .= ($tpMsg='  TakeProfit: '.($prevTP ? $prevTP:'-').' => '.($current ? $current:'-'));
      if (($current=$position->getStopLoss())   != $prevSL) $modification .= ($slMsg='  StopLoss: '  .($prevSL ? $prevSL:'-').' => '.($current ? $current:'-'));
      if (!$modification) throw new plRuntimeException('No modification found in OpenPosition '.$position);

      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = 'position modified: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getOpenPrice().$modification;
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      try {
         $mailMsg = $signal->getName().' Modify '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().$modification;
         foreach (MyFX ::getMailSignalReceivers() as $receiver) {
            mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
         }
      }
      catch (Exception $ex) { Logger ::log($ex, L_ERROR, __CLASS__); }


      // Benachrichtigung per SMS
      try {
         $smsMsg = 'Modified '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().($tpMsg ? "\n".trim($tpMsg):'').($slMsg ? "\n".trim($slMsg):'')."\n\n#".$position->getTicket().'  ('.MyFX ::fxtDate(time(), 'H:i:s').')';
         foreach (MyFX ::getSmsSignalReceivers() as $receiver) {
            //MyFX ::sendSms($receiver, $signal, $smsMsg);
         }
      }
      catch (Exception $ex) { Logger ::log($ex, L_ERROR, __CLASS__); }
   }


   /**
    * Handler für PositionClose-Events eines SimpleTrade-Signals.
    *
    * @param  ClosedPosition $position - die geschlossene Position
    */
   public static function onPositionClose(ClosedPosition $position) {
      $signal = $position->getSignal();

      // Ausgabe in Console
      $consoleMsg = 'position closed: '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().'  Open: '.$position->getOpenPrice().'  Close: '.$position->getClosePrice().'  Profit: '.$position->getProfit(2).'  ('.$position->getCloseTime('H:i:s').')';
      echoPre($consoleMsg);


      // Benachrichtigung per E-Mail
      try {
         $mailMsg = $signal->getName().' Close '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice();
         foreach (MyFX ::getMailSignalReceivers() as $receiver) {
            mail($receiver, $subject=$mailMsg, $msg=$mailMsg);
         }
      }
      catch (Exception $ex) { Logger ::log($ex, L_ERROR, __CLASS__); }


      // Benachrichtigung per SMS
      try {
         $smsMsg = 'Closed '.ucFirst($position->getType()).' '.$position->getLots().' lot '.$position->getSymbol().' @ '.$position->getClosePrice()."\nOpen: ".$position->getOpenPrice()."\n\n#".$position->getTicket().'  ('.$position->getCloseTime('H:i:s').')';
         foreach (MyFX ::getSmsSignalReceivers() as $receiver) {
            MyFX ::sendSms($receiver, $signal, $smsMsg);
         }
      }
      catch (Exception $ex) { Logger ::log($ex, L_ERROR, __CLASS__); }
   }
}
?>