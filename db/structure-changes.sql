CREATE TABLE changes (
     uid INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
     gerrit_uid INTEGER NOT NULL,
     title TEXT,
     owner TEXT,
     owner_avatar TEXT,
     is_active INTEGER,
     is_wip INTEGER,
     community_verified INTEGER,
     community_reviewed INTEGER,
     merger_verified INTEGER,
     merger_reviewed INTEGER,
     ci_verified INTEGER,
     url TEXT,
     priority_points INTEGER,
     patch_size INTEGER,
     last_modified INTEGER,
     created INTEGER,
     comments INTEGER,
     comments_unresolved INTEGER,
     commit_message TEXT,
     branch TEXT,
     involved TEXT,
     debug TEXT
);
