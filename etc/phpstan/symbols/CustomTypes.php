<?php
declare(strict_types=1);

namespace rosasurfer\rt\phpstan;

/**
 * Custom PHPStan type definitions and matching classes to enable IntelliSense and code completion.
 * Add this file to the project's library path and use the types in PHPStan annotations only.
 *
 *
 * @phpstan-type DUKASCOPY_BAR = array{
 *     time      : int,
 *     time_delta: int,
 *     open      : int,
 *     high      : int,
 *     low       : int,
 *     close     : int,
 *     volume    : float,
 * }
 *
 * @phpstan-type DUKASCOPY_BAR_RAW = array{
 *     timeDelta: int,
 *     open     : int,
 *     high     : int,
 *     low      : int,
 *     close    : int,
 *     volume   : float,
 * }
 *
 * @phpstan-type DUKASCOPY_TICK = array{
 *     time_gmt   : int,
 *     time_fxt   : int,
 *     time_millis: int,
 *     timeDelta  : int,
 *     bid        : int,
 *     bidSize    : float,
 *     ask        : int,
 *     askSize    : float,
 * }
 *
 * @phpstan-type DUKASCOPY_TICK_RAW = array{
 *     timeDelta: int,
 *     bid      : int,
 *     bidSize  : float,
 *     ask      : int,
 *     askSize  : float,
 * }
 *
 * @phpstan-type HISTORY_BAR_400 = array{
 *     time : int,
 *     open : float,
 *     high : float,
 *     low  : float,
 *     close: float,
 *     ticks: float,
 * }
 *
 * @phpstan-type HISTORY_BAR_401 = array{
 *     time  : int,
 *     open  : float,
 *     high  : float,
 *     low   : float,
 *     close : float,
 *     spread: int,
 *     ticks : int,
 *     volume: int,
 * }
 *
 * @phpstan-type LOG_ORDER = array{
 *     id         : int,
 *     ticket     : int,
 *     type       : int,
 *     lots       : float,
 *     symbol     : non-empty-string,
 *     openPrice  : float,
 *     openTime   : int,
 *     stopLoss   : float,
 *     takeProfit : float,
 *     closePrice : float,
 *     closeTime  : int,
 *     commission : float,
 *     swap       : float,
 *     profit     : float,
 *     magicNumber: int,
 *     comment    : string,
 * }
 *
 * @phpstan-type LOG_TEST = array{
 *     id             : int,
 *     time           : int,
 *     strategy       : non-empty-string,
 *     reportingId    : int,
 *     reportingSymbol: non-empty-string,
 *     symbol         : non-empty-string,
 *     timeframe      : int,
 *     startTime      : int,
 *     endTime        : int,
 *     barModel       : 0|1|2,
 *     spread         : float,
 *     bars           : int,
 *     ticks          : int,
 * }
 *
 * @phpstan-type RT_POINT_BAR = array{
 *     time : int,
 *     open : int,
 *     high : int,
 *     low  : int,
 *     close: int,
 *     ticks: int,
 * }
 *
 * @phpstan-type RT_PRICE_BAR = array{
 *     time : int,
 *     open : float,
 *     high : float,
 *     low  : float,
 *     close: float,
 *     ticks: int,
 * }
 *
 * @phpstan-type TZ_TRANSITION = array{
 *     ts    : int,
 *     time  : string,
 *     offset: int,
 *     isdst : bool,
 *     abbr  : string,
 * }
 */
final class CustomTypes
{
}

/**
 * Custom PHPStan type for an array holding a normalized Dukascopy price bar.
 * Index 'timeDelta' is replaced by indexes 'time' and 'time_delta' (now FXT).
 *
 * <pre>
 * DUKASCOPY_BAR = array(
 *     time      : int,         // bar open time in FXT
 *     time_delta: int,         // bar offset to 00:00 FXT in seconds
 *     open      : int,         // open value in point
 *     high      : int,         // high value in point
 *     low       : int,         // low value in point
 *     close     : int,         // close value in point
 *     volume    : float,       // volume
 * )
 * </pre>
 *
 * @see \rosasurfer\rt\phpstan\DUKASCOPY_BAR_RAW
 */
final class DUKASCOPY_BAR
{
}

/**
 * Custom PHPStan type for an array holding a raw Dukascopy price bar.
 * PHP representation of the C++ struct DUKASCOPY_BAR.
 *
 * <pre>
 * DUKASCOPY_BAR_RAW = array(
 *     timeDelta: int,          // time difference in seconds since 00:00 GMT
 *     open     : int,          // open value in point
 *     high     : int,          // close value in point
 *     low      : int,          // low value in point
 *     close    : int,          // high value in point
 *     volume   : float,        // volume
 * )
 * </pre>
 */
final class DUKASCOPY_BAR_RAW
{
}

/**
 * Custom PHPStan type for an array holding a normalized Dukascopy tick.
 *
 * <pre>
 * DUKASCOPY_TICK = array(
 *     time_gmt   : int,        // tick timestamp in sec (GMT)
 *     time_fxt   : int,        // tick timestamp in sec (FXT)
 *     time_millis: int,        // fractional seconds in msec
 *     timeDelta  : int,        // time difference in msec since start of the hour
 *     bid        : int,        // bid value in point
 *     bidSize    : float,      // cumulated bid size in lots (min. 1)
 *     ask        : int,        // ask value in point
 *     askSize    : float,      // cumulated ask size in lots (min. 1)
 * )
 * </pre>
 */
final class DUKASCOPY_TICK
{
}

/**
 * Custom PHPStan type for an array holding a raw Dukascopy tick.
 * PHP representation of the C++ struct DUKASCOPY_TICK.
 *
 * <pre>
 * DUKASCOPY_TICK_RAW = array(
 *     timeDelta: int,          // time difference in msec since start of the hour
 *     bid      : int,          // bid value in point
 *     bidSize  : float,        // cumulated bid size in lots (min. 1)
 *     ask      : int,          // ask value in point
 *     askSize  : float,        // cumulated ask size in lots (min. 1)
 * )
 * </pre>
 */
final class DUKASCOPY_TICK_RAW
{
}

/**
 * Custom PHPStan type for an array holding a raw MT4 price bar in format 400.
 *
 * <pre>
 * HISTORY_BAR_400 = array(
 *     time : int,
 *     open : float,
 *     high : float,
 *     low  : float,
 *     close: float,
 *     ticks: float,
 * )
 * </pre>
 */
final class HISTORY_BAR_400
{
}

/**
 * Custom PHPStan type for an array holding a raw MT4 price bar in format 401.
 *
 * <pre>
 * HISTORY_BAR_401 = array(
 *     time  : int,
 *     open  : float,
 *     high  : float,
 *     low   : float,
 *     close : float,
 *     spread: int,
 *     ticks : int,
 *     volume: int,
 * )
 * </pre>
 */
final class HISTORY_BAR_401
{
}

/**
 * Custom PHPStan type for a logfile entry holding the properties of an order.
 *
 * <pre>
 * LOG_ORDER = array(
 *     id         : int,
 *     ticket     : int,
 *     type       : int,
 *     lots       : float,
 *     symbol     : non-empty-string,
 *     openPrice  : float,
 *     openTime   : int,
 *     stopLoss   : float,
 *     takeProfit : float,
 *     closePrice : float,
 *     closeTime  : int,
 *     commission : float,
 *     swap       : float,
 *     profit     : float,
 *     magicNumber: int,
 *     comment    : string,
 * )
 * </pre>
 */
final class LOG_ORDER
{
}

/**
 * Custom PHPStan type for a logfile entry holding the properties of a test.
 *
 * <pre>
 * LOG_TEST = array(
 *     id             : int,
 *     time           : int,                // test creation timestamp (local TZ)
 *     strategy       : non-empty-string,
 *     reportingId    : int,
 *     reportingSymbol: non-empty-string,
 *     symbol         : non-empty-string,
 *     timeframe      : int,
 *     startTime      : int,                // history start timestamp (FXT)
 *     endTime        : int,                // history end timestamp (FXT)
 *     barModel       : 0|1|2,
 *     spread         : float,
 *     bars           : int,
 *     ticks          : int,
 * )
 * </pre>
 */
final class LOG_TEST
{
}

/**
 * Custom PHPStan type for an array holding a price bar quoted in integer values.
 *
 * <pre>
 * RT_POINT_BAR = array(
 *     time : int,          // bar open timestamp in FXT
 *     open : int,          // open price in point
 *     high : int,          // high price in point
 *     low  : int,          // low price in point
 *     close: int,          // close price in point
 *     ticks: int,          // volume (if available) or number of ticks
 * )
 * </pre>
 */
final class RT_POINT_BAR
{
}

/**
 * Custom PHPStan type for an array holding a price bar quoted in float values (real prices).
 *
 * <pre>
 * RT_PRICE_BAR = array(
 *     time : int,          // bar open timestamp in FXT
 *     open : float,        // open price
 *     high : float,        // high price
 *     low  : float,        // low price
 *     close: float,        // close price
 *     ticks: int,          // volume (if available) or number of ticks
 * )
 * </pre>
 */
final class RT_PRICE_BAR
{
}

/**
 * Custom PHPStan type for an array holding a built-in timezone transition.
 *
 * <pre>
 * TZ_TRANSITION = array(
 *     ts    : int,         // timestamp
 *     time  : string,      // time string
 *     offset: int,         // offset to UTC in seconds
 *     isdst : bool,        // whether DST is active
 *     abbr  : string,      // timezone abbreviation
 * )
 * </pre>
 *
 * @see \DateTimeZone::getTransitions()
 */
final class TZ_TRANSITION
{
}
