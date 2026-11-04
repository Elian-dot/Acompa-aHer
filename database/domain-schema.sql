-- Generated from the twelve domain migrations; migrations remain the source of truth.
-- PostgreSQL 16, PostGIS and the existing Laravel users table are prerequisites.
-- Review artifact: do not apply in addition to the Laravel migrations.

BEGIN;

create table "report_categories" ("id" smallserial not null primary key, "code" varchar(50) not null, "name" varchar(100) not null, "is_active" boolean not null default '1', "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "report_categories" add constraint "report_categories_code_unique" unique ("code");

create table "zones" ("id" bigserial not null primary key, "code" varchar(50) not null, "name" varchar(150) not null, "boundary" geometry(multipolygon,4326) not null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

create index "zones_boundary_spatialindex" on "zones" using gist ("boundary");

alter table "zones" add constraint "zones_code_unique" unique ("code");

ALTER TABLE zones ADD CONSTRAINT zones_boundary_valid CHECK (NOT ST_IsEmpty(boundary) AND ST_IsValid(boundary) AND ST_XMin(Box3D(boundary)) >= -180 AND ST_XMax(Box3D(boundary)) <= 180 AND ST_YMin(Box3D(boundary)) >= -90 AND ST_YMax(Box3D(boundary)) <= 90);

create table "emergency_contacts" ("id" bigserial not null primary key, "user_id" bigint not null, "name" text not null, "phone" text not null, "email" text null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "emergency_contacts" add constraint "emergency_contacts_user_id_foreign" foreign key ("user_id") references "users" ("id") on delete cascade;

create index "emergency_contacts_user_id_index" on "emergency_contacts" ("user_id");

create table "reports" ("id" uuid not null, "user_id" bigint null, "report_category_id" smallint not null, "is_anonymous" boolean not null default '1', "location" geometry(point,4326) not null, "occurred_at" timestamp(6) with time zone not null, "description" text null, "status" varchar(20) not null default 'pending', "moderated_by" bigint null, "moderated_at" timestamp(6) with time zone null, "anonymized_at" timestamp(6) with time zone null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "reports" add constraint "reports_user_id_foreign" foreign key ("user_id") references "users" ("id") on delete set null;

alter table "reports" add constraint "reports_report_category_id_foreign" foreign key ("report_category_id") references "report_categories" ("id") on delete restrict;

alter table "reports" add constraint "reports_moderated_by_foreign" foreign key ("moderated_by") references "users" ("id") on delete set null;

create index "reports_location_spatialindex" on "reports" using gist ("location");

create index "reports_report_category_id_occurred_at_index" on "reports" ("report_category_id", "occurred_at");

create index "reports_user_id_index" on "reports" ("user_id");

create index "reports_moderated_by_index" on "reports" ("moderated_by");

alter table "reports" add primary key ("id");

ALTER TABLE reports
    ADD CONSTRAINT reports_uuid_v4 CHECK (id::text ~ '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
    ADD CONSTRAINT reports_anonymous_author CHECK (NOT is_anonymous OR user_id IS NULL),
    ADD CONSTRAINT reports_status_valid CHECK (status IN ('pending', 'verified', 'discarded')),
    ADD CONSTRAINT reports_moderation_consistent CHECK ((status = 'pending' AND moderated_at IS NULL AND moderated_by IS NULL) OR (status IN ('verified', 'discarded') AND moderated_at IS NOT NULL)),
    ADD CONSTRAINT reports_anonymization_consistent CHECK (anonymized_at IS NULL OR (is_anonymous AND user_id IS NULL)),
    ADD CONSTRAINT reports_location_valid CHECK (NOT ST_IsEmpty(location) AND ST_X(location) BETWEEN -180 AND 180 AND ST_Y(location) BETWEEN -90 AND 90);
CREATE INDEX reports_verified_occurred_at_index ON reports (occurred_at) WHERE status = 'verified';

create table "report_attachments" ("id" bigserial not null primary key, "report_id" uuid not null, "disk" varchar(50) not null default 'local', "path" varchar(255) not null, "mime_type" varchar(100) not null, "size_bytes" bigint not null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "report_attachments" add constraint "report_attachments_report_id_foreign" foreign key ("report_id") references "reports" ("id") on delete cascade;

alter table "report_attachments" add constraint "report_attachments_disk_path_unique" unique ("disk", "path");

create index "report_attachments_report_id_index" on "report_attachments" ("report_id");

ALTER TABLE report_attachments ADD CONSTRAINT report_attachments_size_positive CHECK (size_bytes > 0);

create table "journeys" ("id" bigserial not null primary key, "user_id" bigint not null, "status" varchar(20) not null default 'active', "origin" geometry(point,4326) not null, "destination" geometry(point,4326) not null, "route" geometry(linestring,4326) null, "started_at" timestamp(6) with time zone not null, "ended_at" timestamp(6) with time zone null, "last_activity_at" timestamp(6) with time zone not null, "inactivity_seconds" integer not null, "escalation_delay_seconds" integer not null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "journeys" add constraint "journeys_user_id_foreign" foreign key ("user_id") references "users" ("id") on delete cascade;

create index "journeys_user_id_started_at_index" on "journeys" ("user_id", "started_at");

ALTER TABLE journeys
    ADD CONSTRAINT journeys_status_valid CHECK (status IN ('active', 'completed', 'cancelled')),
    ADD CONSTRAINT journeys_lifecycle_consistent CHECK ((status = 'active' AND ended_at IS NULL) OR (status IN ('completed', 'cancelled') AND ended_at IS NOT NULL AND ended_at >= started_at)),
    ADD CONSTRAINT journeys_activity_valid CHECK (last_activity_at >= started_at AND (ended_at IS NULL OR last_activity_at <= ended_at)),
    ADD CONSTRAINT journeys_alert_intervals_positive CHECK (inactivity_seconds > 0 AND escalation_delay_seconds > 0),
    ADD CONSTRAINT journeys_origin_valid CHECK (NOT ST_IsEmpty(origin) AND ST_X(origin) BETWEEN -180 AND 180 AND ST_Y(origin) BETWEEN -90 AND 90),
    ADD CONSTRAINT journeys_destination_valid CHECK (NOT ST_IsEmpty(destination) AND ST_X(destination) BETWEEN -180 AND 180 AND ST_Y(destination) BETWEEN -90 AND 90),
    ADD CONSTRAINT journeys_route_valid CHECK (route IS NULL OR (NOT ST_IsEmpty(route) AND ST_IsValid(route) AND ST_XMin(Box3D(route)) >= -180 AND ST_XMax(Box3D(route)) <= 180 AND ST_YMin(Box3D(route)) >= -90 AND ST_YMax(Box3D(route)) <= 90));
CREATE UNIQUE INDEX journeys_one_active_per_user ON journeys (user_id) WHERE status = 'active';
CREATE INDEX journeys_active_last_activity_index ON journeys (last_activity_at) WHERE status = 'active';
CREATE INDEX journeys_closed_ended_at_index ON journeys (ended_at) WHERE ended_at IS NOT NULL;

create table "journey_participants" ("id" bigserial not null primary key, "journey_id" bigint not null, "emergency_contact_id" bigint not null, "access_token_hash" char(64) not null, "expires_at" timestamp(6) with time zone not null, "accepted_at" timestamp(6) with time zone null, "revoked_at" timestamp(6) with time zone null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "journey_participants" add constraint "journey_participants_journey_id_foreign" foreign key ("journey_id") references "journeys" ("id") on delete cascade;

alter table "journey_participants" add constraint "journey_participants_emergency_contact_id_foreign" foreign key ("emergency_contact_id") references "emergency_contacts" ("id") on delete cascade;

alter table "journey_participants" add constraint "journey_participants_journey_id_emergency_contact_id_unique" unique ("journey_id", "emergency_contact_id");

create index "journey_participants_emergency_contact_id_index" on "journey_participants" ("emergency_contact_id");

alter table "journey_participants" add constraint "journey_participants_access_token_hash_unique" unique ("access_token_hash");

ALTER TABLE journey_participants
    ADD CONSTRAINT journey_participants_token_hash_valid CHECK (access_token_hash ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT journey_participants_expiry_valid CHECK (expires_at > created_at),
    ADD CONSTRAINT journey_participants_acceptance_valid CHECK (accepted_at IS NULL OR (accepted_at >= created_at AND accepted_at < expires_at)),
    ADD CONSTRAINT journey_participants_revocation_valid CHECK (revoked_at IS NULL OR revoked_at >= created_at);

create table "journey_locations" ("id" bigserial not null primary key, "journey_id" bigint not null, "position" geometry(point,4326) not null, "accuracy_meters" decimal(8, 2) not null, "recorded_at" timestamp(6) with time zone not null, "received_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "journey_locations" add constraint "journey_locations_journey_id_foreign" foreign key ("journey_id") references "journeys" ("id") on delete cascade;

alter table "journey_locations" add constraint "journey_locations_journey_id_recorded_at_unique" unique ("journey_id", "recorded_at");

ALTER TABLE journey_locations
    ADD CONSTRAINT journey_locations_accuracy_valid CHECK (accuracy_meters >= 0),
    ADD CONSTRAINT journey_locations_position_valid CHECK (NOT ST_IsEmpty(position) AND ST_X(position) BETWEEN -180 AND 180 AND ST_Y(position) BETWEEN -90 AND 90);

create table "push_subscriptions" ("id" bigserial not null primary key, "user_id" bigint null, "journey_participant_id" bigint null, "endpoint" text not null, "endpoint_hash" char(64) not null, "public_key" text not null, "auth_token" text not null, "content_encoding" varchar(20) not null default 'aes128gcm', "expires_at" timestamp(6) with time zone null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "push_subscriptions" add constraint "push_subscriptions_user_id_foreign" foreign key ("user_id") references "users" ("id") on delete cascade;

alter table "push_subscriptions" add constraint "push_subscriptions_journey_participant_id_foreign" foreign key ("journey_participant_id") references "journey_participants" ("id") on delete cascade;

create index "push_subscriptions_user_id_index" on "push_subscriptions" ("user_id");

create index "push_subscriptions_journey_participant_id_index" on "push_subscriptions" ("journey_participant_id");

alter table "push_subscriptions" add constraint "push_subscriptions_endpoint_hash_unique" unique ("endpoint_hash");

ALTER TABLE push_subscriptions
    ADD CONSTRAINT push_subscriptions_one_owner CHECK (num_nonnulls(user_id, journey_participant_id) = 1),
    ADD CONSTRAINT push_subscriptions_endpoint_hash_valid CHECK (endpoint_hash ~ '^[0-9a-f]{64}$'),
    ADD CONSTRAINT push_subscriptions_encoding_valid CHECK (content_encoding IN ('aes128gcm', 'aesgcm'));

create table "journey_alerts" ("id" bigserial not null primary key, "journey_id" bigint not null, "idempotency_key" uuid not null, "kind" varchar(20) not null, "status" varchar(20) not null default 'preliminary', "inactivity_started_at" timestamp(6) with time zone null, "triggered_at" timestamp(6) with time zone not null, "escalation_due_at" timestamp(6) with time zone null, "confirmed_at" timestamp(6) with time zone null, "acknowledged_at" timestamp(6) with time zone null, "resolved_at" timestamp(6) with time zone null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "journey_alerts" add constraint "journey_alerts_journey_id_foreign" foreign key ("journey_id") references "journeys" ("id") on delete cascade;

alter table "journey_alerts" add constraint "journey_alerts_idempotency_key_unique" unique ("idempotency_key");

ALTER TABLE journey_alerts
    ADD CONSTRAINT journey_alerts_kind_valid CHECK (kind IN ('inactivity', 'emergency')),
    ADD CONSTRAINT journey_alerts_status_valid CHECK (status IN ('preliminary', 'escalated', 'acknowledged', 'resolved')),
    ADD CONSTRAINT journey_alerts_kind_fields_valid CHECK (
        (kind = 'inactivity' AND inactivity_started_at IS NOT NULL AND inactivity_started_at <= triggered_at AND escalation_due_at IS NOT NULL AND escalation_due_at > triggered_at AND confirmed_at IS NULL)
        OR (kind = 'emergency' AND inactivity_started_at IS NULL AND escalation_due_at IS NULL AND confirmed_at IS NOT NULL AND confirmed_at <= triggered_at AND status <> 'preliminary')
    ),
    ADD CONSTRAINT journey_alerts_acknowledgment_valid CHECK (status <> 'acknowledged' OR acknowledged_at IS NOT NULL),
    ADD CONSTRAINT journey_alerts_resolution_valid CHECK ((status = 'resolved') = (resolved_at IS NOT NULL)),
    ADD CONSTRAINT journey_alerts_timestamps_valid CHECK ((acknowledged_at IS NULL OR acknowledged_at >= triggered_at) AND (resolved_at IS NULL OR resolved_at >= triggered_at));
CREATE UNIQUE INDEX journey_alerts_inactivity_cycle_unique ON journey_alerts (journey_id, inactivity_started_at) WHERE kind = 'inactivity';
CREATE INDEX journey_alerts_journey_triggered_index ON journey_alerts (journey_id, triggered_at);
CREATE INDEX journey_alerts_pending_escalation_index ON journey_alerts (escalation_due_at) WHERE status = 'preliminary';

create table "alert_deliveries" ("id" bigserial not null primary key, "journey_alert_id" bigint not null, "user_id" bigint null, "journey_participant_id" bigint null, "channel" varchar(20) not null, "stage" varchar(20) not null, "status" varchar(20) not null default 'pending', "attempts" integer not null default '0', "last_attempt_at" timestamp(6) with time zone null, "sent_at" timestamp(6) with time zone null, "failure_code" varchar(100) null, "created_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP, "updated_at" timestamp(6) with time zone not null default CURRENT_TIMESTAMP);

alter table "alert_deliveries" add constraint "alert_deliveries_journey_alert_id_foreign" foreign key ("journey_alert_id") references "journey_alerts" ("id") on delete cascade;

alter table "alert_deliveries" add constraint "alert_deliveries_user_id_foreign" foreign key ("user_id") references "users" ("id") on delete cascade;

alter table "alert_deliveries" add constraint "alert_deliveries_journey_participant_id_foreign" foreign key ("journey_participant_id") references "journey_participants" ("id") on delete cascade;

create index "alert_deliveries_user_id_index" on "alert_deliveries" ("user_id");

create index "alert_deliveries_journey_participant_id_index" on "alert_deliveries" ("journey_participant_id");

ALTER TABLE alert_deliveries
    ADD CONSTRAINT alert_deliveries_one_recipient CHECK (num_nonnulls(user_id, journey_participant_id) = 1),
    ADD CONSTRAINT alert_deliveries_channel_valid CHECK (channel IN ('websocket', 'push')),
    ADD CONSTRAINT alert_deliveries_stage_valid CHECK (stage IN ('preliminary', 'escalated')),
    ADD CONSTRAINT alert_deliveries_status_valid CHECK (status IN ('pending', 'sent', 'failed')),
    ADD CONSTRAINT alert_deliveries_attempts_valid CHECK (attempts >= 0 AND ((attempts = 0 AND last_attempt_at IS NULL) OR (attempts > 0 AND last_attempt_at IS NOT NULL))),
    ADD CONSTRAINT alert_deliveries_sent_valid CHECK ((status = 'sent') = (sent_at IS NOT NULL) AND (sent_at IS NULL OR attempts > 0));
CREATE UNIQUE INDEX alert_deliveries_user_unique ON alert_deliveries (journey_alert_id, user_id, channel, stage) WHERE user_id IS NOT NULL;
CREATE UNIQUE INDEX alert_deliveries_participant_unique ON alert_deliveries (journey_alert_id, journey_participant_id, channel, stage) WHERE journey_participant_id IS NOT NULL;
CREATE INDEX alert_deliveries_pending_index ON alert_deliveries (journey_alert_id) WHERE status IN ('pending', 'failed');

CREATE OR REPLACE FUNCTION camina_guard_owner() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.user_id IS DISTINCT FROM OLD.user_id THEN
        RAISE EXCEPTION 'Ownership cannot be reassigned' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER emergency_contacts_owner_immutable BEFORE UPDATE ON emergency_contacts
    FOR EACH ROW EXECUTE FUNCTION camina_guard_owner();
CREATE TRIGGER journeys_owner_immutable BEFORE UPDATE ON journeys
    FOR EACH ROW EXECUTE FUNCTION camina_guard_owner();

CREATE OR REPLACE FUNCTION camina_guard_journey_lifecycle() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.started_at IS DISTINCT FROM OLD.started_at
        OR NEW.inactivity_seconds IS DISTINCT FROM OLD.inactivity_seconds
        OR NEW.escalation_delay_seconds IS DISTINCT FROM OLD.escalation_delay_seconds THEN
        RAISE EXCEPTION 'Journey start and alert interval snapshots are immutable' USING ERRCODE = '23514';
    END IF;
    IF NEW.last_activity_at < OLD.last_activity_at THEN
        RAISE EXCEPTION 'Journey activity cannot move backwards' USING ERRCODE = '23514';
    END IF;
    IF OLD.status <> 'active' AND (
        NEW.status IS DISTINCT FROM OLD.status OR NEW.ended_at IS DISTINCT FROM OLD.ended_at
        OR NEW.last_activity_at IS DISTINCT FROM OLD.last_activity_at
        OR NEW.origin IS DISTINCT FROM OLD.origin OR NEW.destination IS DISTINCT FROM OLD.destination
        OR NEW.route IS DISTINCT FROM OLD.route
    ) THEN
        RAISE EXCEPTION 'A closed journey cannot be reopened or have its retention extended' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER journeys_lifecycle_guard BEFORE UPDATE ON journeys
    FOR EACH ROW EXECUTE FUNCTION camina_guard_journey_lifecycle();

CREATE OR REPLACE FUNCTION camina_guard_participant() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    journey_owner bigint;
    journey_status text;
    contact_owner bigint;
BEGIN
    IF TG_OP = 'UPDATE' AND (NEW.journey_id IS DISTINCT FROM OLD.journey_id OR NEW.emergency_contact_id IS DISTINCT FROM OLD.emergency_contact_id) THEN
        RAISE EXCEPTION 'Participation cannot be moved to another journey or contact' USING ERRCODE = '23514';
    END IF;
    SELECT user_id, status INTO journey_owner, journey_status FROM journeys WHERE id = NEW.journey_id FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Journey does not exist' USING ERRCODE = '23503';
    END IF;
    SELECT user_id INTO contact_owner FROM emergency_contacts WHERE id = NEW.emergency_contact_id FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Emergency contact does not exist' USING ERRCODE = '23503';
    END IF;
    IF journey_owner <> contact_owner THEN
        RAISE EXCEPTION 'A journey can only share with its owners contacts' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'INSERT' AND journey_status <> 'active' THEN
        RAISE EXCEPTION 'Cannot share a closed journey' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER journey_participants_integrity BEFORE INSERT OR UPDATE ON journey_participants
    FOR EACH ROW EXECUTE FUNCTION camina_guard_participant();

CREATE OR REPLACE FUNCTION camina_guard_location() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    journey_status text;
    journey_started_at timestamptz;
BEGIN
    IF TG_OP = 'UPDATE' THEN
        RAISE EXCEPTION 'Location samples are immutable' USING ERRCODE = '23514';
    END IF;
    SELECT status, started_at INTO journey_status, journey_started_at FROM journeys WHERE id = NEW.journey_id FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Journey does not exist' USING ERRCODE = '23503';
    END IF;
    IF journey_status <> 'active' OR NEW.recorded_at < journey_started_at THEN
        RAISE EXCEPTION 'Location requires an active journey and a valid recording time' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER journey_locations_integrity BEFORE INSERT OR UPDATE ON journey_locations
    FOR EACH ROW EXECUTE FUNCTION camina_guard_location();

CREATE OR REPLACE FUNCTION camina_guard_alert() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    journey_status text;
    journey_started_at timestamptz;
BEGIN
    IF TG_OP = 'UPDATE' AND (NEW.journey_id IS DISTINCT FROM OLD.journey_id OR NEW.idempotency_key IS DISTINCT FROM OLD.idempotency_key OR NEW.inactivity_started_at IS DISTINCT FROM OLD.inactivity_started_at OR NEW.kind IS DISTINCT FROM OLD.kind) THEN
        RAISE EXCEPTION 'Alert identity and inactivity cycle are immutable' USING ERRCODE = '23514';
    END IF;
    SELECT status, started_at INTO journey_status, journey_started_at FROM journeys WHERE id = NEW.journey_id FOR SHARE;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Journey does not exist' USING ERRCODE = '23503';
    END IF;
    IF journey_status <> 'active' AND (TG_OP = 'INSERT' OR (NEW.status = 'escalated' AND NEW.status IS DISTINCT FROM OLD.status)) THEN
        RAISE EXCEPTION 'Cannot trigger or escalate an alert for a closed journey' USING ERRCODE = '23514';
    END IF;
    IF NEW.triggered_at < journey_started_at OR NEW.inactivity_started_at < journey_started_at THEN
        RAISE EXCEPTION 'Alert timestamps cannot precede the journey' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER journey_alerts_integrity BEFORE INSERT OR UPDATE ON journey_alerts
    FOR EACH ROW EXECUTE FUNCTION camina_guard_alert();

CREATE OR REPLACE FUNCTION camina_guard_delivery() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    alert_journey bigint;
    journey_owner bigint;
    journey_status text;
    participant_journey bigint;
    participant_revoked_at timestamptz;
    participant_expires_at timestamptz;
    alert_status text;
    alert_kind text;
BEGIN
    IF TG_OP = 'UPDATE' AND (NEW.journey_alert_id IS DISTINCT FROM OLD.journey_alert_id OR NEW.user_id IS DISTINCT FROM OLD.user_id OR NEW.journey_participant_id IS DISTINCT FROM OLD.journey_participant_id OR NEW.channel IS DISTINCT FROM OLD.channel OR NEW.stage IS DISTINCT FROM OLD.stage) THEN
        RAISE EXCEPTION 'Delivery identity and recipient cannot be reassigned' USING ERRCODE = '23514';
    END IF;
    SELECT j.id, j.user_id, j.status, a.status, a.kind INTO alert_journey, journey_owner, journey_status, alert_status, alert_kind
        FROM journeys j JOIN journey_alerts a ON a.journey_id = j.id
        WHERE a.id = NEW.journey_alert_id FOR SHARE OF j;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'Journey alert does not exist' USING ERRCODE = '23503';
    END IF;
    IF NEW.user_id IS NOT NULL AND NEW.user_id <> journey_owner THEN
        RAISE EXCEPTION 'User recipient must own the alerted journey' USING ERRCODE = '23514';
    END IF;
    IF NEW.journey_participant_id IS NOT NULL THEN
        SELECT journey_id, revoked_at, expires_at INTO participant_journey, participant_revoked_at, participant_expires_at FROM journey_participants WHERE id = NEW.journey_participant_id FOR SHARE;
        IF NOT FOUND THEN
            RAISE EXCEPTION 'Journey participant does not exist' USING ERRCODE = '23503';
        END IF;
        IF participant_journey <> alert_journey OR NEW.stage = 'preliminary' THEN
            RAISE EXCEPTION 'Contact recipient must belong to this journey and receive only escalated alerts' USING ERRCODE = '23514';
        END IF;
        IF TG_OP = 'INSERT' AND (participant_revoked_at IS NOT NULL OR participant_expires_at <= CURRENT_TIMESTAMP) THEN
            RAISE EXCEPTION 'Cannot enqueue delivery to an expired or revoked participant' USING ERRCODE = '23514';
        END IF;
    END IF;
    IF TG_OP = 'INSERT' AND journey_status <> 'active' THEN
        RAISE EXCEPTION 'Cannot enqueue delivery for a closed journey' USING ERRCODE = '23514';
    END IF;
    IF TG_OP = 'INSERT' AND ((NEW.stage = 'escalated' AND alert_status <> 'escalated') OR (NEW.stage = 'preliminary' AND alert_kind <> 'inactivity')) THEN
        RAISE EXCEPTION 'Delivery stage must match the alert state' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER alert_deliveries_integrity BEFORE INSERT OR UPDATE ON alert_deliveries
    FOR EACH ROW EXECUTE FUNCTION camina_guard_delivery();

CREATE OR REPLACE FUNCTION camina_guard_anonymity() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF OLD.is_anonymous AND (NOT NEW.is_anonymous OR NEW.user_id IS NOT NULL) THEN
        RAISE EXCEPTION 'Anonymous report authorship cannot be restored' USING ERRCODE = '23514';
    END IF;
    RETURN NEW;
END;
$$;
CREATE TRIGGER reports_anonymity_irreversible BEFORE UPDATE ON reports
    FOR EACH ROW EXECUTE FUNCTION camina_guard_anonymity();

COMMIT;
