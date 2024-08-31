
##### This package and its subpackages operate on history data in Dukascopy format.

---


### Dukascopy historical data feed  

Dukascopy provides history with separate bid and ask timeseries in GMT covering weekends and holidays. Data of the current
day is available the earliest at the next day (`GMT`). In Rosatrader bid and ask prices are merged to median, converted to
`FXT` (see below) and stored in Rosatrader format. Weekend and holiday data is not stored. Spreads can be re-defined at testing.

---


### URLs

<table>
<tr>
    <td colspan="2"> <b>Web site</b> </td>
</tr>
<tr>
    <td> Historical data feed (1) </td>
    <td> https://www.dukascopy.com/swiss/english/marketwatch/historical/ </td>
</tr>
<tr>
    <td> Historical data feed (2) </td>
    <td> https://www.dukascopy.com/trading-tools/widgets/quotes/historical_data_feed </td>
</tr>
<tr>
    <td colspan="2"><br></td>
</tr>

<tr>
    <td colspan="2"> <b>History start times</b> </td>
</tr>
<tr>
    <td> All instruments </td>
    <td> http://datafeed.dukascopy.com/datafeed/metadata/HistoryStart.bi5 </td>
</tr>
<tr>
    <td> Single instrument </td>
    <td> http://datafeed.dukascopy.com/datafeed/AUDUSD/metadata/HistoryStart.bi5 </td>
</tr>
<tr>
    <td colspan="2"><br></td>
</tr>

<tr>
    <td colspan="2"> <b>Timeseries M1</b> </td>
</tr>
<tr>
    <td> Bid </td>
    <td> http://datafeed.dukascopy.com/datafeed/AUDUSD/2013/00/10/BID_candles_min_1.bi5 </td>
</tr>
<tr>
    <td> Ask </td>
    <td> http://datafeed.dukascopy.com/datafeed/AUDUSD/2013/11/31/ASK_candles_min_1.bi5 </td>
</tr>
</table>

M1 history is available one file per calendar day since history start. During trade breaks data indicates the last available
close price (OHLC) and a volume of zero (V=0). Months are counted starting from zero (January = 00).
Data is [LZMA](https://en.wikipedia.org/wiki/Lempel%E2%80%93Ziv%E2%80%93Markov_chain_algorithm) compressed.

---


### Data structures

Structure `DUKASCOPY_HISTORY_START` describes the history start times of all available timeframes of a symbol:
```C++
// big-endian
struct DUKASCOPY_HISTORY_START {        // -- offset --- size --- description -------------------------------------------------------
    char      start;                    //         0        1     struct start marker (always NULL)
    char      length;                   //         1        1     length of the following symbol name
    char      symbol[length];           //         2 {length}     symbol name w/o terminating NULL character
    int64     count;                    //  variable        8     number of history start records to follow (always 4)
    DUKASCOPY_HISTORYSTART_RECORD;      //  variable       16     PERIOD_TICK
    DUKASCOPY_HISTORYSTART_RECORD;      //  variable       16     PERIOD_M1
    DUKASCOPY_HISTORYSTART_RECORD;      //  variable       16     PERIOD_H1
    DUKASCOPY_HISTORYSTART_RECORD;      //  variable       16     PERIOD_D1
};                                      // ------------------------------------------------------------------------------------------
                                        //                = 2 + {length} + {count} * sizeof(DUKASCOPY_HISTORYSTART_RECORD)
```
---

Structure `DUKASCOPY_HISTORYSTART_RECORD` describes the history start time of a single timeframe:
```C++
// big-endian
struct DUKASCOPY_HISTORYSTART_RECORD {  // -- offset --- size --- description -------------------------------------------------------
    int64 timeframe;                    //         0        8     period in msec (-1: same as 0 = PERIOD_TICK)
    int64 time;                         //         8        8     start time as a Java timestamp (INT_MAX: no history avaliable)
};                                      // ------------------------------------------------------------------------------------------
                                        //               = 16
```
---

Structure `DUKASCOPY_BAR` describes a single history bar:
```C++
// big-endian
struct DUKASCOPY_BAR {                  // -- offset --- size --- description -------------------------------------------------------
    uint  timeDelta;                    //         0        4     time difference in seconds since 00:00 GMT of the day
    uint  open;                         //         4        4     in point
    uint  close;                        //         8        4     in point
    uint  low;                          //        12        4     in point
    uint  high;                         //        16        4     in point
    float volume;                       //        20        4     in traded units
};                                      // ------------------------------------------------------------------------------------------
                                        //               = 24
```
---

Structure `DUKASCOPY_TICK` describes a single tick:
```C++
// big-endian
struct DUKASCOPY_TICK {                 // -- offset --- size --- description -------------------------------------------------------
    uint  timeDelta;                    //         0        4     time difference in msec since start of the hour
    uint  ask;                          //         4        4     in point
    uint  bid;                          //         8        4     in point
    float askSize;                      //        12        4     cumulated ask size in lot (min. 1)
    float bidSize;                      //        16        4     cumulated bid size in lot (min. 1)
};                                      // ------------------------------------------------------------------------------------------
                                        //               = 20
```
---


### Timezones

Internally Rosatrader uses a timezone called `FXT` (Forex standard time) which is a synonym for `America/New_York+0700`. All
history data and times are stored and displayed in it. In this timezone trading days start and end always at Midnight, no
matter of the current DST (daylight saving time) status. It's the only trading related timezone without DST irregularities
and without so called Sunday candles. If a time is displayed without timezone identifier it is assumed to be in `FXT`.
```
        +------------++------------+------------+------------+------------+------------++------------+------------++------------+
GMT:    |   Sunday   ||   Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday   ||  Saturday  |   Sunday   ||   Monday   |
        +------------++------------+------------+------------+------------+------------++------------+------------++------------+
     +------------++------------+------------+------------+------------+------------++------------+------------++------------+
FXT: |   Sunday   ||   Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday   ||  Saturday  |   Sunday   ||   Monday   |
     +------------++------------+------------+------------+------------+------------++------------+------------++------------+
```
@todo: check info from Zorro forum:  http://www.opserver.de/ubb7/ubbthreads.php?ubb=showflat&Number=463361#Post463345
