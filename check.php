print_r(DB::select("SELECT count(*) as n FROM pg_tables WHERE schemaname='public' AND rowsecurity=true"));
