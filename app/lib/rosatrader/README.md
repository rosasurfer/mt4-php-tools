
##### Classes in this namespace operate on price data in the application's own format.

---


### Data structures

Struct `RT_POINT_BAR` describes a price bar quoted in integer values:
```C++
struct RT_POINT_BAR {           // -- offset --- size --- description --------------------------------
    uint time;                  //         0        4     FXT timestamp (seconds since 01.01.1970 FXT)
    uint open;                  //         4        4     in point
    uint high;                  //         8        4     in point
    uint low;                   //        12        4     in point
    uint close;                 //        16        4     in point
    uint ticks;                 //        20        4     volume (if available) or number of ticks
};                              // -------------------------------------------------------------------
                                //               = 24
```
---

Struct `RT_TICK` describes a single tick:
```C++
struct RT_TICK {                // -- offset --- size --- description --------------------------------
    uint timeDelta;             //         0        4     milliseconds since start of the hour
    uint bid;                   //         4        4     in point
    uint ask;                   //         8        4     in point
};                              // -------------------------------------------------------------------
                                //               = 12
```
---
