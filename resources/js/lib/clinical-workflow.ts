export type RoleAssignment = 'perawat' | 'dokter' | 'keduanya';
export type NurseOptionalAssignment = 'perawat' | 'nonaktif';
export type AnthropometryMode = 'ringkas' | 'lengkap';

export interface ClinicalWorkflowConfig {
    umum_complaint_details: RoleAssignment;
    umum_system_review: RoleAssignment;
    umum_nutrition_needs: NurseOptionalAssignment;
    umum_anthropometry_mode: AnthropometryMode;
    gigi_pain_details: RoleAssignment;
    gigi_mucosa_observation: RoleAssignment;
    gigi_additional_assessment: NurseOptionalAssignment;
    dokter_soap_tab: boolean;
    dokter_repeat_vitals: boolean;
}

/** Recommended clinic defaults (matches App\ClinicalWorkflowSettings::defaults()). */
export const defaultClinicalWorkflow: ClinicalWorkflowConfig = {
    umum_complaint_details: 'dokter',
    umum_system_review: 'dokter',
    umum_nutrition_needs: 'perawat',
    umum_anthropometry_mode: 'ringkas',
    gigi_pain_details: 'perawat',
    gigi_mucosa_observation: 'perawat',
    gigi_additional_assessment: 'perawat',
    dokter_soap_tab: true,
    dokter_repeat_vitals: true,
};

/** Legacy fallback when a component is rendered in isolation without workflowSettings prop. */
export const legacyClinicalWorkflow: ClinicalWorkflowConfig = {
    umum_complaint_details: 'perawat',
    umum_system_review: 'perawat',
    umum_nutrition_needs: 'perawat',
    umum_anthropometry_mode: 'ringkas',
    gigi_pain_details: 'perawat',
    gigi_mucosa_observation: 'perawat',
    gigi_additional_assessment: 'perawat',
    dokter_soap_tab: true,
    dokter_repeat_vitals: true,
};

export function resolveClinicalWorkflow(
    config?: Partial<ClinicalWorkflowConfig> | null,
    fallback: ClinicalWorkflowConfig = legacyClinicalWorkflow,
): ClinicalWorkflowConfig {
    if (!config) return fallback;

    return {
        umum_complaint_details: config.umum_complaint_details ?? fallback.umum_complaint_details,
        umum_system_review: config.umum_system_review ?? fallback.umum_system_review,
        umum_nutrition_needs: config.umum_nutrition_needs ?? fallback.umum_nutrition_needs,
        umum_anthropometry_mode: config.umum_anthropometry_mode ?? fallback.umum_anthropometry_mode,
        gigi_pain_details: config.gigi_pain_details ?? fallback.gigi_pain_details,
        gigi_mucosa_observation: config.gigi_mucosa_observation ?? fallback.gigi_mucosa_observation,
        gigi_additional_assessment: config.gigi_additional_assessment ?? fallback.gigi_additional_assessment,
        dokter_soap_tab: typeof config.dokter_soap_tab === 'boolean' ? config.dokter_soap_tab : fallback.dokter_soap_tab,
        dokter_repeat_vitals: typeof config.dokter_repeat_vitals === 'boolean' ? config.dokter_repeat_vitals : fallback.dokter_repeat_vitals,
    };
}

export interface DoctorComplaintDetails {
    location?: string;
    character?: string;
    pattern?: string;
    triggers?: string;
    relief?: string;
    progression?: string;
    progression_notes?: string;
    pain_scale?: string | number | null;
    notes?: string;
}

export interface DoctorDentalPainDetails {
    location?: string;
    pain_scale?: string | number | null;
    duration?: string;
    triggers?: string[];
    triggers_other?: string;
    character?: string;
    relief?: string;
    impact?: string;
    progression?: string;
    progression_notes?: string;
}

export interface DoctorMucosaObservation {
    lidah?: string;
    pipi?: string;
    bibir?: string;
    palatum?: string;
    gusi?: string;
    catatan?: Partial<Record<'lidah' | 'pipi' | 'bibir' | 'palatum' | 'gusi', string>>;
    kelainan_mukosa?: string;
    observasi_mulut?: string;
}

export interface DoctorClinicalAssessment {
    complaint_details?: DoctorComplaintDetails;
    dental_pain?: DoctorDentalPainDetails;
    mucosa_observation?: DoctorMucosaObservation;
}
