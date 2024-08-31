//
// Dukascopy structure DUKASCOPY_HISTORY_START (symbol format in files "HistoryStart.bi5")
//
//
// big-endian
// struct DUKASCOPY_HISTORY_START {     // -- offset --- size --- description -----------------------------------------------
//     char      start;                 //         0        1     symbol start marker (always NULL)
//     char      length;                //         1        1     length of the following symbol name
//     char      symbol[length];        //         2 {length}     symbol name (no terminating NULL character)
//     int64    count;                  //  variable        8     number of timeframe start records to follow (always 4)
//     DUKASCOPY_TIMEFRAME_START;       //  variable       16     PERIOD_TICK     
//     DUKASCOPY_TIMEFRAME_START;       //  variable       16     PERIOD_M1
//     DUKASCOPY_TIMEFRAME_START;       //  variable       16     PERIOD_H1
//     DUKASCOPY_TIMEFRAME_START;       //  variable       16     PERIOD_D1
// };                                   // ----------------------------------------------------------------------------------
//                                      //                = 2 + {length} + {count} * sizeof(DUKASCOPY_TIMEFRAME_START)

template    "Dukascopy History Starts (multi)"
description "Files 'HistoryStart.bi5' (multiple symbols)"

applies_to  file
fixed_start 0
multiple
big-endian

begin
    { endsection
        byte                 "Start Marker"
        byte                 "SymbolLength"
        string SymbolLength  "Symbol"
        int64                "Timeframes"
                             
        int64                "Period (msec)"
        JavaDateTime         "Start Time"
                             
        int64                "Period (msec)"
        JavaDateTime         "Start Time"
                             
        int64                "Period (msec)"
        JavaDateTime         "Start Time"
                             
        int64                "Period (msec)"
        JavaDateTime         "Start Time"
    }[2]
end
