//
// Dukascopy structure DUKASCOPY_TIMEFRAME_START (timeframe format in files "HistoryStart.bi5")
//
//
// struct DUKASCOPY_TIMEFRAME_START {   // -- offset --- size --- description -----------------------------------------------
//     int64 timeframe;                 //         0        8     period length in minutes as a Java timestamp (msec)
//     int64 time;                      //         8        8     start time as a Java timestamp (msec), PHP_INT_MAX = n/a
// };                                   // ----------------------------------------------------------------------------------
//                                      //               = 16

template    "Dukascopy History Start (single)"
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
