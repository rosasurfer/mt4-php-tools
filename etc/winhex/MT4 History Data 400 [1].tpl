//
// MetaTrader structure HISTORY_BAR_400
//
// HistoryFile Barformat v400 (bis Build 509), entspricht dem MetaQuotes-Struct RateInfo
//
//
// @see  https://github.com/rosasurfer/mt4-expander/blob/master/header/struct/mt4/HistoryBar400.h
//

template    "MT4 History Data v400 [1]"
description "Files '*.hst'"

applies_to   file
fixed_start  148
requires    -148 "90 01"              // Version = 400
multiple

begin
   endsection

   UNIXDateTime "Time"
   double       "Open"
   move         8
   double       "High"
   move         -16
   double       "Low"
   move         8
   double       "Close"
   double       "Ticks"
end
