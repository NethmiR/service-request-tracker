<?php

// Holds the length limits of service request text fields and history remarks.
// Migrations and Form Requests read these values, and the frontend mirrors the title and description limits.

namespace App\Support\ServiceRequests;

final class ServiceRequestLimits
{
    public const int TITLE_MAX_LENGTH = 150;

    public const int DESCRIPTION_MAX_LENGTH = 5000;

    public const int REMARK_MAX_LENGTH = 1000;
}
