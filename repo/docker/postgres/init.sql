-- Enable pg_trgm extension for fuzzy text matching (duplicate detection)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Enable unaccent for normalized search
CREATE EXTENSION IF NOT EXISTS unaccent;
