import type { LineString } from 'geojson';
import type { Coordinate } from '@/types/safety';

export type JourneyLocation = Coordinate & {
    accuracy_meters: number;
    recorded_at: string;
};

export type ParticipantStatus = 'pending' | 'opened' | 'revoked' | 'expired';

export type Participant = {
    id: number;
    name: string;
    status: ParticipantStatus;
    opened_at: string | null;
    expires_at: string;
    push_enabled: boolean;
};

export type Delivery = {
    recipient: string;
    is_owner: boolean;
    stage: 'preliminary' | 'escalated';
    status: 'pending' | 'sent' | 'failed';
    failure_code: string | null;
    attempts: number;
    sent_at: string | null;
};

export type AlertState = {
    id: number;
    status: 'preliminary' | 'escalated' | 'acknowledged' | 'resolved';
    triggered_at: string;
    escalation_due_at: string | null;
    acknowledged_at: string | null;
    deliveries: Delivery[];
};

export type JourneyState = {
    id: number;
    status: 'active' | 'completed' | 'cancelled';
    started_at: string;
    ended_at: string | null;
    last_activity_at: string;
    paused_until: string | null;
    inactivity_seconds: number;
    escalation_delay_seconds: number;
    inactivity_due_at: string;
    origin: Coordinate;
    destination: Coordinate;
    route: LineString | null;
    location: JourneyLocation | null;
    alert: AlertState | null;
    participants: Participant[];
    server_time: string;
};

export type ShareEntry = {
    participant_id: number;
    name: string;
    tracking_url: string;
    sms_url: string;
};

export type TrackingState =
    | {
          state: 'revoked' | 'expired' | 'ended';
          owner_name: string;
          server_time: string;
      }
    | {
          state: 'active';
          owner_name: string;
          server_time: string;
          started_at: string;
          expires_at: string;
          paused_until: string | null;
          origin: Coordinate;
          destination: Coordinate;
          route: LineString | null;
          location: JourneyLocation | null;
          alert: {
              status: 'escalated' | 'acknowledged' | 'resolved';
              triggered_at: string;
              acknowledged_at: string | null;
          } | null;
          push_enabled: boolean;
      };

export type HourlyIncidence = {
    hours: { hour: number; count: number | null }[];
    bands: {
        id: string;
        label: string;
        from: number;
        to: number;
        count: number | null;
    }[];
    highest_hours: number[];
    lowest_ranges: { from: number; to: number }[];
    meta: {
        status: 'available' | 'insufficient_data';
        sample_size: number;
        minimum_sample: number;
        minimum_group: number;
        confidence: 'insufficient' | 'low' | 'medium' | 'high';
        period_days: number;
        from: string;
        to: string;
        source: string;
        notice: string;
    };
};

export type CommunityStatistics = {
    totals: {
        verified: number;
        previous_period: number | null;
        change_percent: number | null;
    };
    categories: {
        code: string;
        name: string;
        count: number | null;
        grouped: boolean;
    }[];
    months: {
        month: string;
        label: string;
        count: number | null;
        partial: boolean;
    }[];
    meta: {
        status: 'available' | 'insufficient_data';
        sample_size: number;
        minimum_sample: number;
        minimum_group: number;
        period_days: number;
        from: string;
        to: string;
        source: string;
        zone: string;
        notice: string;
    };
};
