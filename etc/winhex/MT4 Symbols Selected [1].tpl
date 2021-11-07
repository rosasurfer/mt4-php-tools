//
// MetaTrader structure SYMBOL_SELECTED: Dateiformat "symbols.sel"
//
//
// @see  https://github.com/rosasurfer/mt4-expander/blob/master/header/struct/mt4/SymbolSelected.h
//

template    "MT4 Symbols Selected [1]"
description "File 'symbols.sel'"

applies_to   file
fixed_start  4
requires    -4 "90 01"              // Version = 400
multiple

begin
   { endsection
      char[12]     "Symbol"
      move 4
      uint32       "SYMBOL.index"
      uint32       "(undocumented DWORD)"
      endsection

      uint32       "Group Index"
      uint32       "(undocumented DWORD)"
      endsection

      move -20
      uint32       "Digits"
      move 16
      double       "Point"
      uint32       "Fixed Spread"
      uint32       "(undocumented DWORD)"
      endsection

      uint32       "Tick Type: 0=Up, 1=Down, 2=n/a"
      uint16       "(undocumented WORD)"
      uint16       "(undocumented WORD 2)"
      move -2
      hex   2      "(undocumented HEX 2)"
      endsection

      UNIXDateTime "Time"
      uint32       "(undocumented DWORD)"
      endsection

      double       "Bid"
      double       "Ask"
      double       "Session High"
      double       "Session Low"
      hex  16      "(undocumented)"
      double       "Bid"                                // Bid und Ask werden wiederholt
      double       "Ask"
   }
end
