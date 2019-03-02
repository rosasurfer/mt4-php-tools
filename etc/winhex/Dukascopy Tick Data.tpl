//
// Dukascopy structure DUKASCOPY_TICK (file format "{Hour}h_ticks.bin")
//
//
// big-endian
// struct DUKASCOPY_TICK {              // -- offset --- size --- description -----------------------------------------------
//     uint  timeDelta;                 //         0        4     time difference in msec since start of the hour
//     uint  ask;                       //         4        4     in points
//     uint  bid;                       //         8        4     in points
//     float askSize;                   //        12        4     cumulated ask size in lots (min. 1)
//     float bidSize;                   //        16        4     cumulated bid size in lots (min. 1)
// };                                   // ----------------------------------------------------------------------------------
//                                      //               = 20

template    "Dukascopy Tick Data"
description "Files '{Hour}h_ticks.bin'"

applies_to  file
big-endian
multiple

begin
    { endsection
        uint32   "TimeDelta (msec)"
        move  4
        uint32   "Bid"
        move -8
        uint32   "Ask"
        move  8
        float    "BidSize"
        move -8
        float    "AskSize"
        move  4
    }[32]
end
