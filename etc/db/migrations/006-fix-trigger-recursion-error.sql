
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- RosaSymbols: fix error "too many levels of trigger recursion"
drop trigger if exists tr_rosasymbol_before_update;
drop trigger if exists tr_rosasymbol_after_update;

create trigger tr_rosasymbol_after_update after update on t_rosasymbol
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_rosasymbol set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- DukascopySymbols
drop trigger if exists tr_dukascopysymbol_before_update;
drop trigger if exists tr_dukascopysymbol_after_update;

create trigger tr_dukascopysymbol_after_update after update on t_dukascopysymbol
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_dukascopysymbol set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Tests
drop trigger if exists tr_test_before_update;
drop trigger if exists tr_test_after_update;

create trigger tr_test_after_update after update on t_test
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_test set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Orders
drop trigger if exists tr_order_before_update;
drop trigger if exists tr_order_after_update;

create trigger tr_order_after_update after update on t_order
when (new.modified is null or (new.modified=old.modified and new.modified!=datetime('now')))
begin
   update t_order set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
