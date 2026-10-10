<?php

namespace App\Modules\Marketplace\Domain\Coverage;

/** DP-027 governs only the synthetic local exercise, not operational coverage. */
final class LocalZoneSelectionPolicy
{
    /** @param iterable<ZoneCandidate> $candidates */
    public function select(iterable $candidates): ZoneSelection
    {
        $winner = null;
        $tie = false;
        foreach ($candidates as $candidate) {
            if ($winner === null || $candidate->priority > $winner->priority) {
                $winner = $candidate;
                $tie = false;
            } elseif ($candidate->priority === $winner->priority && $candidate->publicId !== $winner->publicId) {
                $tie = true;
            }
        }

        return $winner === null ? ZoneSelection::outside() : ($tie ? ZoneSelection::ambiguous() : ZoneSelection::selected($winner));
    }
}
