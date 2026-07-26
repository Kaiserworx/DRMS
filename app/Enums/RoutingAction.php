<?php

namespace App\Enums;

use App\Models\DeploymentSetting;

enum RoutingAction: string
{
    case SubmitByUnit = 'submit_by_unit';
    case ReceiveAtManagingOffice = 'receive_at_managing_office';
    case ForwardToUpstreamOffice = 'forward_to_upstream_office';
    case RecordUpstreamReceipt = 'record_upstream_receipt';
    case ReturnFromUpstreamOffice = 'return_from_upstream_office';
    case MarkForDistribution = 'mark_for_distribution';
    case AssignRecipientUnit = 'assign_recipient_unit';
    case PlaceInReceivingBox = 'place_in_receiving_box';
    case ClaimByRecipientUnit = 'claim_by_recipient_unit';
    case Cancel = 'cancel';

    public function label(): string
    {
        $settings = DeploymentSetting::current();
        $managingOffice = $settings?->managing_office_name ?? 'Managing Office';
        $upstreamOffice = $settings?->upstream_office_label ?? 'Upstream Office';
        $levelOneUnit = $settings?->level_1_unit_label ?? 'Unit';
        $receivingBox = $settings?->receiving_box_label ?? 'Receiving Box';

        return match ($this) {
            self::SubmitByUnit => "Submit by {$levelOneUnit}",
            self::ReceiveAtManagingOffice => "Receive at {$managingOffice}",
            self::ForwardToUpstreamOffice => "Forward to {$upstreamOffice}",
            self::RecordUpstreamReceipt => "Record {$upstreamOffice} Receipt",
            self::ReturnFromUpstreamOffice => "Return from {$upstreamOffice}",
            self::MarkForDistribution => 'Mark for Distribution',
            self::AssignRecipientUnit => 'Assign Recipient Unit',
            self::PlaceInReceivingBox => "Place in {$receivingBox}",
            self::ClaimByRecipientUnit => "Confirm Receipt by {$levelOneUnit}",
            self::Cancel => 'Cancel Document',
        };
    }
}
