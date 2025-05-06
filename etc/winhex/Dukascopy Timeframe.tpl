//
// Dukascopy struct DUKASCOPY_TIMEFRAME (timeframe format in files "HistoryStart.bi5")
//
//
// big-endian
// struct DUKASCOPY_TIMEFRAME {     // -- offset --- size --- description ----------------------------------------------------
//     int64 period;                //         0        8     period length in minutes as a Java timestamp (msec)
//     int64 starttime;             //         8        8     history start time as a Java timestamp (msec), PHP_INT_MAX = n/a
// };                               // ---------------------------------------------------------------------------------------
//                                  //               = 16

template    "Dukascopy Timeframe"
description "Files 'HistoryStart.bi5' (single symbol)"

applies_to  file
big-endian

begin
    int64         "Period (msec)"
    JavaDateTime  "Start Time"

    int64         "Period (msec)"
    JavaDateTime  "Start Time"

    int64         "Period (msec)"
    JavaDateTime  "Start Time"

    int64         "Period (msec)"
    JavaDateTime  "Start Time"
    endsection
end
