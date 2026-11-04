<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
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
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER reports_anonymity_irreversible ON reports;
            DROP TRIGGER alert_deliveries_integrity ON alert_deliveries;
            DROP TRIGGER journey_alerts_integrity ON journey_alerts;
            DROP TRIGGER journey_locations_integrity ON journey_locations;
            DROP TRIGGER journey_participants_integrity ON journey_participants;
            DROP TRIGGER journeys_lifecycle_guard ON journeys;
            DROP TRIGGER journeys_owner_immutable ON journeys;
            DROP TRIGGER emergency_contacts_owner_immutable ON emergency_contacts;
            DROP FUNCTION camina_guard_anonymity();
            DROP FUNCTION camina_guard_delivery();
            DROP FUNCTION camina_guard_alert();
            DROP FUNCTION camina_guard_location();
            DROP FUNCTION camina_guard_participant();
            DROP FUNCTION camina_guard_journey_lifecycle();
            DROP FUNCTION camina_guard_owner();
            SQL);
    }
};
