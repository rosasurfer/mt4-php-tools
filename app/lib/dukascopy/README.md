
### Dukascopy historical data feed

Dukascopy provides history with separate bid and ask timeseries in GMT covering weekends and holidays. Data of the current
day is available the earliest at the next day (`GMT`). In Rosatrader bid and ask prices are merged to median, converted to
`FXT` (see below) and stored in Rosatrader format (`RT_PRICE_BAR`). Weekend and holiday data is not stored. Spreads can be
re-defined at testing.


### URLs

<table>
<tr>
    <td colspan="2"> <b>Web site</b> </td>
</tr>
<tr>
    <td> Historical data feed </td>
    <td> https://www.dukascopy.com/swiss/english/marketwatch/historical/ </td>
</tr>
<tr>
    <td colspan="2"> </td>
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
    <td colspan="2"> </td>
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
close price (OHLC) and a volume of zero (V=0). Months are counted starting with zero (January = 00).
Data is [LZMA](https://en.wikipedia.org/wiki/Lempel%E2%80%93Ziv%E2%80%93Markov_chain_algorithm) compressed.


### Timezones

Internally Rosatrader uses everywhere a virtual timezone called `FXT`. By default all history and times are stored and
displayed in that timezone. `FXT` (Forex standard time) essentially is the timezone `America/New_York` shifted eastward by
7 hours. In this timezone trading days start and end always at Midnight, no matter of the current DST (daylight saving time)
status. It's the only timezone suitable for trading without DST irregularities and without the infamous Sunday candles. If a
time is displayed without timezone identifier it is assumed to be in `FXT`.
```
        +------------++------------+------------+------------+------------+------------++------------+------------++------------+
GMT:    |   Sunday   ||   Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday   ||  Saturday  |   Sunday   ||   Monday   |
        +------------++------------+------------+------------+------------+------------++------------+------------++------------+
     +------------++------------+------------+------------+------------+------------++------------+------------++------------+
FXT: |   Sunday   ||   Monday   |  Tuesday   | Wednesday  |  Thursday  |   Friday   ||  Saturday  |   Sunday   ||   Monday   |
     +------------++------------+------------+------------+------------+------------++------------+------------++------------+
```
@todo: check info from Zorro forum:  http://www.opserver.de/ubb7/ubbthreads.php?ubb=showflat&Number=463361#Post463345
