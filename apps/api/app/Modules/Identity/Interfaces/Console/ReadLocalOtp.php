<?php

namespace App\Modules\Identity\Interfaces\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class ReadLocalOtp extends Command
{
    protected $signature = 'identity:local-otp {challenge : Public challenge ULID}';

    protected $description = 'Read local-only OTP delivery; never pipe output to logs.';

    public function handle(): int
    {
        if (! app()->environment('local') || ! $this->input->isInteractive()) {
            $this->error('Interactive local environment required.');

            return self::FAILURE;
        }
        $row = DB::table('identity_otp_challenges')->where('public_id', $this->argument('challenge'))
            ->whereNull('consumed_at')->where('expires_at', '>', now())->where('attempts', '<', 5)->first();
        if ($row === null) {
            $this->error('Challenge unavailable.');

            return self::FAILURE;
        }
        $this->line(Crypt::decryptString($row->code_ciphertext));

        return self::SUCCESS;
    }
}
