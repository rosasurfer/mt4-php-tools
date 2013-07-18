<?
/**
 * Zentraler HTTP-Request-Handler
 */
require(dirName(__FILE__).'/WEB-INF/config.php');

FrontController ::processRequest();


/*
$timezone    = new DateTimeZone('Europe/Minsk');
$transitions = $timezone->getTransitions();
echoPre($transitions);


array([  0] => array([ts    ] => -1441158600
                     [time  ] => 1924-05-01T22:10:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [  1] => array([ts    ] => -1247536800
                     [time  ] => 1930-06-20T22:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [  2] => array([ts    ] => -899780400
                     [time  ] => 1941-06-27T21:00:00+0000
                     [offset] => 7200
                     [isdst ] => 1
                     [abbr  ] => CEST),

      [  3] => array([ts    ] => -857257200
                     [time  ] => 1942-11-02T01:00:00+0000
                     [offset] => 3600
                     [isdst ] => 0
                     [abbr  ] => CET),

      [  4] => array([ts    ] => -844556400
                     [time  ] => 1943-03-29T01:00:00+0000
                     [offset] => 7200
                     [isdst ] => 1
                     [abbr  ] => CEST),

      [  5] => array([ts    ] => -828226800
                     [time  ] => 1943-10-04T01:00:00+0000
                     [offset] => 3600
                     [isdst ] => 0
                     [abbr  ] => CET),

      [  6] => array([ts    ] => -812502000
                     [time  ] => 1944-04-03T01:00:00+0000
                     [offset] => 7200
                     [isdst ] => 1
                     [abbr  ] => CEST),

      [  7] => array([ts    ] => -804650400
                     [time  ] => 1944-07-02T22:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [  8] => array([ts    ] => 354920400
                     [time  ] => 1981-03-31T21:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [  9] => array([ts    ] => 370728000
                     [time  ] => 1981-09-30T20:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 10] => array([ts    ] => 386456400
                     [time  ] => 1982-03-31T21:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 11] => array([ts    ] => 402264000
                     [time  ] => 1982-09-30T20:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 12] => array([ts    ] => 417992400
                     [time  ] => 1983-03-31T21:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 13] => array([ts    ] => 433800000
                     [time  ] => 1983-09-30T20:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 14] => array([ts    ] => 449614800
                     [time  ] => 1984-03-31T21:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 15] => array([ts    ] => 465346800
                     [time  ] => 1984-09-29T23:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 16] => array([ts    ] => 481071600
                     [time  ] => 1985-03-30T23:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 17] => array([ts    ] => 496796400
                     [time  ] => 1985-09-28T23:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 18] => array([ts    ] => 512521200
                     [time  ] => 1986-03-29T23:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 19] => array([ts    ] => 528246000
                     [time  ] => 1986-09-27T23:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 20] => array([ts    ] => 543970800
                     [time  ] => 1987-03-28T23:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 21] => array([ts    ] => 559695600
                     [time  ] => 1987-09-26T23:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 22] => array([ts    ] => 575420400
                     [time  ] => 1988-03-26T23:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 23] => array([ts    ] => 591145200
                     [time  ] => 1988-09-24T23:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 24] => array([ts    ] => 606870000
                     [time  ] => 1989-03-25T23:00:00+0000
                     [offset] => 14400
                     [isdst ] => 1
                     [abbr  ] => MSD),

      [ 25] => array([ts    ] => 622594800
                     [time  ] => 1989-09-23T23:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 26] => array([ts    ] => 631141200
                     [time  ] => 1989-12-31T21:00:00+0000
                     [offset] => 10800
                     [isdst ] => 0
                     [abbr  ] => MSK),

      [ 27] => array([ts    ] => 670374000
                     [time  ] => 1991-03-30T23:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 28] => array([ts    ] => 686102400
                     [time  ] => 1991-09-29T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 29] => array([ts    ] => 701820000
                     [time  ] => 1992-03-28T22:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 30] => array([ts    ] => 717544800
                     [time  ] => 1992-09-26T22:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 31] => array([ts    ] => 733276800
                     [time  ] => 1993-03-28T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 32] => array([ts    ] => 749001600
                     [time  ] => 1993-09-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 33] => array([ts    ] => 764726400
                     [time  ] => 1994-03-27T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 34] => array([ts    ] => 780451200
                     [time  ] => 1994-09-25T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 35] => array([ts    ] => 796176000
                     [time  ] => 1995-03-26T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 36] => array([ts    ] => 811900800
                     [time  ] => 1995-09-24T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 37] => array([ts    ] => 828230400
                     [time  ] => 1996-03-31T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 38] => array([ts    ] => 846374400
                     [time  ] => 1996-10-27T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 39] => array([ts    ] => 859680000
                     [time  ] => 1997-03-30T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 40] => array([ts    ] => 877824000
                     [time  ] => 1997-10-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 41] => array([ts    ] => 891129600
                     [time  ] => 1998-03-29T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 42] => array([ts    ] => 909273600
                     [time  ] => 1998-10-25T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 43] => array([ts    ] => 922579200
                     [time  ] => 1999-03-28T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 44] => array([ts    ] => 941328000
                     [time  ] => 1999-10-31T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 45] => array([ts    ] => 954028800
                     [time  ] => 2000-03-26T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 46] => array([ts    ] => 972777600
                     [time  ] => 2000-10-29T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 47] => array([ts    ] => 985478400
                     [time  ] => 2001-03-25T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 48] => array([ts    ] => 1004227200
                     [time  ] => 2001-10-28T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 49] => array([ts    ] => 1017532800
                     [time  ] => 2002-03-31T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 50] => array([ts    ] => 1035676800
                     [time  ] => 2002-10-27T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 51] => array([ts    ] => 1048982400
                     [time  ] => 2003-03-30T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 52] => array([ts    ] => 1067126400
                     [time  ] => 2003-10-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 53] => array([ts    ] => 1080432000
                     [time  ] => 2004-03-28T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 54] => array([ts    ] => 1099180800
                     [time  ] => 2004-10-31T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 55] => array([ts    ] => 1111881600
                     [time  ] => 2005-03-27T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 56] => array([ts    ] => 1130630400
                     [time  ] => 2005-10-30T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 57] => array([ts    ] => 1143331200
                     [time  ] => 2006-03-26T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 58] => array([ts    ] => 1162080000
                     [time  ] => 2006-10-29T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 59] => array([ts    ] => 1174780800
                     [time  ] => 2007-03-25T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 60] => array([ts    ] => 1193529600
                     [time  ] => 2007-10-28T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 61] => array([ts    ] => 1206835200
                     [time  ] => 2008-03-30T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 62] => array([ts    ] => 1224979200
                     [time  ] => 2008-10-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 63] => array([ts    ] => 1238284800
                     [time  ] => 2009-03-29T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 64] => array([ts    ] => 1256428800
                     [time  ] => 2009-10-25T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 65] => array([ts    ] => 1269734400
                     [time  ] => 2010-03-28T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 66] => array([ts    ] => 1288483200
                     [time  ] => 2010-10-31T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 67] => array([ts    ] => 1301184000
                     [time  ] => 2011-03-27T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 68] => array([ts    ] => 1319932800
                     [time  ] => 2011-10-30T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 69] => array([ts    ] => 1332633600
                     [time  ] => 2012-03-25T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 70] => array([ts    ] => 1351382400
                     [time  ] => 2012-10-28T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 71] => array([ts    ] => 1364688000
                     [time  ] => 2013-03-31T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 72] => array([ts    ] => 1382832000
                     [time  ] => 2013-10-27T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 73] => array([ts    ] => 1396137600
                     [time  ] => 2014-03-30T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 74] => array([ts    ] => 1414281600
                     [time  ] => 2014-10-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 75] => array([ts    ] => 1427587200
                     [time  ] => 2015-03-29T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 76] => array([ts    ] => 1445731200
                     [time  ] => 2015-10-25T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 77] => array([ts    ] => 1459036800
                     [time  ] => 2016-03-27T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 78] => array([ts    ] => 1477785600
                     [time  ] => 2016-10-30T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 79] => array([ts    ] => 1490486400
                     [time  ] => 2017-03-26T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 80] => array([ts    ] => 1509235200
                     [time  ] => 2017-10-29T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 81] => array([ts    ] => 1521936000
                     [time  ] => 2018-03-25T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 82] => array([ts    ] => 1540684800
                     [time  ] => 2018-10-28T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 83] => array([ts    ] => 1553990400
                     [time  ] => 2019-03-31T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 84] => array([ts    ] => 1572134400
                     [time  ] => 2019-10-27T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 85] => array([ts    ] => 1585440000
                     [time  ] => 2020-03-29T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 86] => array([ts    ] => 1603584000
                     [time  ] => 2020-10-25T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 87] => array([ts    ] => 1616889600
                     [time  ] => 2021-03-28T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 88] => array([ts    ] => 1635638400
                     [time  ] => 2021-10-31T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 89] => array([ts    ] => 1648339200
                     [time  ] => 2022-03-27T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 90] => array([ts    ] => 1667088000
                     [time  ] => 2022-10-30T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 91] => array([ts    ] => 1679788800
                     [time  ] => 2023-03-26T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 92] => array([ts    ] => 1698537600
                     [time  ] => 2023-10-29T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 93] => array([ts    ] => 1711843200
                     [time  ] => 2024-03-31T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 94] => array([ts    ] => 1729987200
                     [time  ] => 2024-10-27T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 95] => array([ts    ] => 1743292800
                     [time  ] => 2025-03-30T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 96] => array([ts    ] => 1761436800
                     [time  ] => 2025-10-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 97] => array([ts    ] => 1774742400
                     [time  ] => 2026-03-29T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [ 98] => array([ts    ] => 1792886400
                     [time  ] => 2026-10-25T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [ 99] => array([ts    ] => 1806192000
                     [time  ] => 2027-03-28T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [100] => array([ts    ] => 1824940800
                     [time  ] => 2027-10-31T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [101] => array([ts    ] => 1837641600
                     [time  ] => 2028-03-26T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [102] => array([ts    ] => 1856390400
                     [time  ] => 2028-10-29T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [103] => array([ts    ] => 1869091200
                     [time  ] => 2029-03-25T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [104] => array([ts    ] => 1887840000
                     [time  ] => 2029-10-28T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [105] => array([ts    ] => 1901145600
                     [time  ] => 2030-03-31T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [106] => array([ts    ] => 1919289600
                     [time  ] => 2030-10-27T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [107] => array([ts    ] => 1932595200
                     [time  ] => 2031-03-30T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [108] => array([ts    ] => 1950739200
                     [time  ] => 2031-10-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [109] => array([ts    ] => 1964044800
                     [time  ] => 2032-03-28T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [110] => array([ts    ] => 1982793600
                     [time  ] => 2032-10-31T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [111] => array([ts    ] => 1995494400
                     [time  ] => 2033-03-27T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [112] => array([ts    ] => 2014243200
                     [time  ] => 2033-10-30T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [113] => array([ts    ] => 2026944000
                     [time  ] => 2034-03-26T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [114] => array([ts    ] => 2045692800
                     [time  ] => 2034-10-29T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [115] => array([ts    ] => 2058393600
                     [time  ] => 2035-03-25T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [116] => array([ts    ] => 2077142400
                     [time  ] => 2035-10-28T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [117] => array([ts    ] => 2090448000
                     [time  ] => 2036-03-30T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [118] => array([ts    ] => 2108592000
                     [time  ] => 2036-10-26T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET),

      [119] => array([ts    ] => 2121897600
                     [time  ] => 2037-03-29T00:00:00+0000
                     [offset] => 10800
                     [isdst ] => 1
                     [abbr  ] => EEST),

      [120] => array([ts    ] => 2140041600
                     [time  ] => 2037-10-25T00:00:00+0000
                     [offset] => 7200
                     [isdst ] => 0
                     [abbr  ] => EET)
)
*/
?>
