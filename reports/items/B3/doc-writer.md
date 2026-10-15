ITEM: B3
PAGES: D:\Sandbox\yt-30-10-2026\ticketflow\docs\user\tickets.md (updated, section "Consulter un ticket")
DOCUMENTED: Required behaviour 1, 2, 3 (and 4 only as "no 500"); AC1, AC2, AC3, AC4 (covered by the general "no ticket with this id" wording). AC5 is unchanged behaviour already documented (404 on PATCH).
NOT DOCUMENTED: Required behaviour 4 "does not write to the database": the verdict notes no test covers it and the spec has no AC for it, so it is not stated. AC6 is a test regression criterion with no user-facing content.
