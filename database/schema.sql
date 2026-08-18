BEGIN;

CREATE TYPE user_role AS ENUM ('guild_leader', 'micro_guild_leader', 'developer', 'party_leader', 'member');
CREATE TYPE player_class AS ENUM ('melee', 'archer', 'mage', 'healer', 'bard', 'tank');
CREATE TYPE activity_type AS ENUM ('prime', 'activity', 'mini_activity');
CREATE TYPE earning_status AS ENUM ('pending', 'paid', 'cancelled');
CREATE TYPE payout_status AS ENUM ('draft', 'calculated', 'paid', 'cancelled');
CREATE TYPE payout_player_status AS ENUM ('pending', 'paid', 'cancelled');
CREATE TYPE auction_status AS ENUM ('draft', 'active', 'finished', 'cancelled');
CREATE TYPE treasury_transaction_type AS ENUM ('auction_income', 'payout', 'manual_income', 'manual_expense', 'adjustment');
CREATE TYPE item_transaction_type AS ENUM ('loot_income', 'issue', 'auction_reserve', 'auction_release', 'auction_sale', 'adjustment');

CREATE TABLE users (
  id BIGSERIAL PRIMARY KEY,
  discord_id VARCHAR(32) NOT NULL UNIQUE,
  discord_username VARCHAR(255) NOT NULL,
  discord_display_name VARCHAR(255),
  discord_avatar VARCHAR(255),
  role user_role NOT NULL DEFAULT 'member',
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE groups (
  id BIGSERIAL PRIMARY KEY, name VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE players (
  id BIGSERIAL PRIMARY KEY, user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE SET NULL,
  group_id BIGINT REFERENCES groups(id) ON DELETE SET NULL,
  nickname VARCHAR(120) NOT NULL UNIQUE, class player_class NOT NULL, is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX players_group_idx ON players(group_id);

CREATE TABLE activity_definitions (
  id BIGSERIAL PRIMARY KEY, name VARCHAR(160) NOT NULL, type activity_type NOT NULL, is_active BOOLEAN NOT NULL DEFAULT true,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(name, type)
);
CREATE TABLE activities (
  id BIGSERIAL PRIMARY KEY, activity_definition_id BIGINT NOT NULL REFERENCES activity_definitions(id),
  occurred_at TIMESTAMPTZ NOT NULL, gold_value BIGINT,
  created_by BIGINT NOT NULL REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (gold_value IS NULL OR gold_value >= 0)
);
CREATE INDEX activities_occurred_idx ON activities(occurred_at DESC);
CREATE TABLE activity_players (
  id BIGSERIAL PRIMARY KEY, activity_id BIGINT NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
  player_id BIGINT NOT NULL REFERENCES players(id), created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(activity_id, player_id)
);
CREATE TABLE activity_loot (
  id BIGSERIAL PRIMARY KEY, activity_id BIGINT NOT NULL REFERENCES activities(id), item_name VARCHAR(255) NOT NULL,
  quantity BIGINT NOT NULL, unit_price BIGINT NOT NULL, created_by BIGINT NOT NULL REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (quantity > 0), CHECK (unit_price >= 0)
);
CREATE TABLE prime_player_earnings (
  id BIGSERIAL PRIMARY KEY, activity_id BIGINT NOT NULL REFERENCES activities(id), player_id BIGINT NOT NULL REFERENCES players(id),
  nickname_snapshot VARCHAR(120) NOT NULL, prime_gold_value_snapshot BIGINT NOT NULL, participants_count_snapshot INTEGER NOT NULL,
  player_share BIGINT NOT NULL, status earning_status NOT NULL DEFAULT 'pending', payout_id BIGINT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(activity_id, player_id),
  CHECK (prime_gold_value_snapshot >= 0), CHECK (participants_count_snapshot > 0), CHECK (player_share >= 0)
);

CREATE TABLE treasury_items (
  id BIGSERIAL PRIMARY KEY, item_name VARCHAR(255) NOT NULL UNIQUE, quantity BIGINT NOT NULL DEFAULT 0,
  reserved_quantity BIGINT NOT NULL DEFAULT 0, unit_value BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (quantity >= 0), CHECK (reserved_quantity >= 0), CHECK (reserved_quantity <= quantity), CHECK (unit_value >= 0)
);
CREATE TABLE treasury_item_transactions (
  id BIGSERIAL PRIMARY KEY, treasury_item_id BIGINT NOT NULL REFERENCES treasury_items(id), type item_transaction_type NOT NULL,
  quantity_delta BIGINT NOT NULL, recipient_player_id BIGINT REFERENCES players(id), source_activity_id BIGINT REFERENCES activities(id),
  auction_id BIGINT, reason TEXT, created_by BIGINT NOT NULL REFERENCES users(id), created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (quantity_delta <> 0)
);
CREATE TABLE treasury_transactions (
  id BIGSERIAL PRIMARY KEY, type treasury_transaction_type NOT NULL, amount BIGINT NOT NULL, balance_after BIGINT NOT NULL,
  description TEXT, related_entity_type VARCHAR(100), related_entity_id BIGINT, created_by BIGINT REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), CHECK (balance_after >= 0)
);
CREATE TABLE treasury_token_settings (
  id SMALLINT PRIMARY KEY DEFAULT 1, token_count BIGINT NOT NULL DEFAULT 0, token_unit_value BIGINT NOT NULL DEFAULT 0,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), CHECK (id = 1), CHECK (token_count >= 0), CHECK (token_unit_value >= 0)
);
INSERT INTO treasury_token_settings (id, token_count, token_unit_value) VALUES (1, 0, 0);

CREATE TABLE auctions (
  id BIGSERIAL PRIMARY KEY, treasury_item_id BIGINT NOT NULL REFERENCES treasury_items(id), quantity BIGINT NOT NULL,
  starting_bid BIGINT NOT NULL, minimum_step BIGINT NOT NULL, ends_at TIMESTAMPTZ NOT NULL,
  status auction_status NOT NULL DEFAULT 'draft', winner_player_id BIGINT REFERENCES players(id), winning_bid BIGINT,
  created_by BIGINT NOT NULL REFERENCES users(id), finished_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (quantity > 0), CHECK (starting_bid >= 0), CHECK (minimum_step > 0), CHECK (winning_bid IS NULL OR winning_bid >= 0)
);
ALTER TABLE treasury_item_transactions ADD CONSTRAINT treasury_item_transactions_auction_fk FOREIGN KEY (auction_id) REFERENCES auctions(id);
CREATE TABLE auction_bids (
  id BIGSERIAL PRIMARY KEY, auction_id BIGINT NOT NULL REFERENCES auctions(id), player_id BIGINT NOT NULL REFERENCES players(id),
  amount BIGINT NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), CHECK (amount >= 0)
);
CREATE INDEX auction_bids_rank_idx ON auction_bids(auction_id, amount DESC, created_at ASC, id ASC);

CREATE TABLE payouts (
  id BIGSERIAL PRIMARY KEY, period_from DATE NOT NULL, period_to DATE NOT NULL, status payout_status NOT NULL DEFAULT 'draft',
  total_amount BIGINT NOT NULL DEFAULT 0, calculated_at TIMESTAMPTZ, paid_at TIMESTAMPTZ, created_by BIGINT NOT NULL REFERENCES users(id),
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK (period_from <= period_to), CHECK (total_amount >= 0)
);
ALTER TABLE prime_player_earnings ADD CONSTRAINT prime_player_earnings_payout_fk FOREIGN KEY (payout_id) REFERENCES payouts(id);
CREATE TABLE payout_activities (
  payout_id BIGINT NOT NULL REFERENCES payouts(id), activity_id BIGINT NOT NULL REFERENCES activities(id),
  PRIMARY KEY (payout_id, activity_id), UNIQUE(activity_id)
);
CREATE TABLE payout_players (
  id BIGSERIAL PRIMARY KEY, payout_id BIGINT NOT NULL REFERENCES payouts(id), player_id BIGINT NOT NULL REFERENCES players(id),
  nickname_snapshot VARCHAR(120) NOT NULL, prime_attendance_percentage_snapshot NUMERIC(5,2) NOT NULL,
  primes_count INTEGER NOT NULL, mini_activities_count INTEGER NOT NULL, amount BIGINT NOT NULL,
  status payout_player_status NOT NULL DEFAULT 'pending', paid_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now(), UNIQUE(payout_id, player_id),
  CHECK (prime_attendance_percentage_snapshot BETWEEN 0 AND 100), CHECK (primes_count >= 0),
  CHECK (mini_activities_count >= 0), CHECK (amount >= 0)
);

CREATE TABLE notifications (
  id UUID PRIMARY KEY, user_id BIGINT REFERENCES users(id) ON DELETE CASCADE, type VARCHAR(255) NOT NULL,
  data JSONB NOT NULL, read_at TIMESTAMPTZ, created_at TIMESTAMPTZ NOT NULL DEFAULT now(), updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE audit_logs (
  id BIGSERIAL PRIMARY KEY, user_id BIGINT REFERENCES users(id) ON DELETE SET NULL, action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(160) NOT NULL, entity_id BIGINT, old_values JSONB, new_values JSONB,
  ip_address INET, created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX audit_entity_idx ON audit_logs(entity_type, entity_id, created_at DESC);

COMMIT;
