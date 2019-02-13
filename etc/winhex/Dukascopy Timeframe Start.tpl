//
// Dukascopy structure DUKASCOPY_TIMEFRAME_START (timeframe format in files "HistoryStart.bi5")
//
//
// struct DUKASCOPY_TIMEFRAME_START {   // -- offset --- size --- description -----------------------------------------------
//     uint64 timeframe;                //         0        8     period length in minutes as a Java timestamp (msec)
//     uint64 time;                     //         8        8     start time as a Java timestamp (msec)
// };                                   // ----------------------------------------------------------------------------------
//                                      //               = 16

template    "Dukascopy Timeframe Start"
description "Files 'HistoryStart.bi5'"

applies_to  file
big-endian

begin
    endsection

    int64         "Period (msec)"
    JavaDateTime  "Time"
    endsection

    int64         "Period (msec)"
    JavaDateTime  "Time"
    endsection

    int64         "Period (msec)"
    JavaDateTime  "Time"
    endsection

    int64         "Period (msec)"
    JavaDateTime  "Time"
    endsection
end
