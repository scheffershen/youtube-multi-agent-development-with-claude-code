ITEM: F2
PAGES: D:\Sandbox\yt-30-10-2026\ticketflow\docs\user\tickets.md (updated, "Lister les tickets" section, new subsection "Filtrer par statut")
DOCUMENTED: Required behaviour 1-6 and 8 (item 8 is internal, so no user text); AC1-AC9 (filter, empty result, 400 body, case sensitivity, empty value). AC10 and AC11 are regression criteria. The existing 422 text on the create and status-change sections is unchanged.
NOT DOCUMENTED: Required behaviour 7 (no 500 for a string status) and AC12, because they are internal guarantees or test-only. Non-string `status` such as `status[]` is out of scope in the spec. The verdict is PASS with no failures or uncovered criteria.
