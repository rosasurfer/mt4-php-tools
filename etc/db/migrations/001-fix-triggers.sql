
pragma foreign_keys       = on;
pragma recursive_triggers = on;
begin;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- fix conditional operators in trigger definitions
-- Instruments
drop trigger if exists tr_instrument_before_update;
drop trigger if exists tr_instrument_after_update;

create trigger tr_instrument_before_update before update on t_instrument
when (new.modified is null or new.modified = old.modified)
begin
   update t_instrument set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Tests
drop trigger if exists tr_test_before_update;
drop trigger if exists tr_test_after_update;

create trigger tr_test_before_update before update on t_test
when (new.modified is null or new.modified = old.modified)
begin
   update t_test set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
-- Orders
drop trigger if exists tr_order_before_update;
drop trigger if exists tr_order_after_update;

create trigger tr_order_before_update before update on t_order
when (new.modified is null or new.modified = old.modified)
begin
   update t_order set modified = datetime('now') where id = new.id;
end;


-- --------------------------------------------------------------------------------------------------------------------------------------------------------------------------
commit;
pragma foreign_keys       = on;
pragma recursive_triggers = on;
vacuum;
