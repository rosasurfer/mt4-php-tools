//
// Dukascopy structure DUKASCOPY_HISTORY_START (symbol format in files "HistoryStart.bi5")
//
//
// struct DUKASCOPY_HISTORY_START {     // -- offset --- size --- description -----------------------------------------------
//     char      start;                 //         0        1     symbol start marker (always NULL)
//     char      length;                //         1        1     length of the following symbol name
//     char      symbol[length];        //         2 {length}     symbol name (no terminating NULL character)
//     int64    count;                  //  variable        8     number of timeframe start records to follow
//     {record};                        //  variable       16     struct DUKASCOPY_TIMEFRAME_START
//     ...                              //  variable       16     struct DUKASCOPY_TIMEFRAME_START
//     {record};                        //  variable       16     struct DUKASCOPY_TIMEFRAME_START
// };                                   // ----------------------------------------------------------------------------------
//                                      //                = 2 + {length} + {count}*16

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
