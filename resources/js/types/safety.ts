import type { FeatureCollection, LineString } from 'geojson';

export type MapConfiguration = {
    center: [number, number];
    zoom: number;
    tileUrl: string;
    coverageAvailable: boolean;
    coverageSource: string;
    coverageSourceUrl: string;
    coverageRetrievedAt: string;
};
export type Category = { id: number; code: string; name: string };
export type Coordinate = { latitude: number; longitude: number };
export type HeatmapMeta = {
    from: string;
    to: string;
    period_days: number;
    timezone: string;
    night_start_hour: number;
    night_end_hour: number;
    minimum_reports: number;
    cell_meters: number;
    status: 'available' | 'insufficient_data';
    notice: string;
};
export type MapPayload = {
    coverage: FeatureCollection;
    heatmap: FeatureCollection & { meta: HeatmapMeta };
};
export type Contact = {
    id: number;
    name: string;
    phone: string;
    email: string | null;
};
export type WalkingRoute = {
    id: number;
    distance_meters: number;
    duration_seconds: number;
    geometry: LineString;
    safety_score: null;
    evidence_status: 'insufficient_data';
};
export type WalkingRouteResponse = {
    data: WalkingRoute[];
    meta: {
        status: 'available' | 'no_route';
        provider: string;
        queried_at: string;
        notice: string;
    };
};
export type ModerationReport = {
    id: string;
    category: string;
    description: string | null;
    is_anonymous: boolean;
    occurred_at: string;
    status: 'pending' | 'verified' | 'discarded';
    moderation_reason: string | null;
    moderated_at: string | null;
    attachments: { id: number; mime_type: string; size_bytes: number }[];
};
