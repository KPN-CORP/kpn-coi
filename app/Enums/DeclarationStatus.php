<?php

declare(strict_types=1);

namespace App\Enums;

enum DeclarationStatus: string
{
    case Draft = 'draft';

    case Submitted = 'submitted';

    case Approved = 'approved';

    case Rejected = 'rejected';

    case RevisionRequired = 'revision_required';
}