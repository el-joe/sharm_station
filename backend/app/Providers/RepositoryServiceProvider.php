<?php

namespace App\Providers;

use App\Repositories\Contracts\AddonRepositoryInterface;
use App\Repositories\Contracts\AiSuggestionRepositoryInterface;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\BuildingRepositoryInterface;
use App\Repositories\Contracts\CommissionSettingRepositoryInterface;
use App\Repositories\Contracts\ContactMessageRepositoryInterface;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use App\Repositories\Contracts\DestinationRepositoryInterface;
use App\Repositories\Contracts\GoogleReviewRepositoryInterface;
use App\Repositories\Contracts\McpTokenRepositoryInterface;
use App\Repositories\Contracts\NationalityGroupRepositoryInterface;
use App\Repositories\Contracts\NewsletterSubscriberRepositoryInterface;
use App\Repositories\Contracts\PackageRepositoryInterface;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use App\Repositories\Contracts\PaymentMethodRepositoryInterface;
use App\Repositories\Contracts\PaymentProofRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\ProductPriceRepositoryInterface;
use App\Repositories\Contracts\RoomRepositoryInterface;
use App\Repositories\Contracts\SeasonPriceRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\TransferFixedRouteRepositoryInterface;
use App\Repositories\Contracts\TransferHubRepositoryInterface;
use App\Repositories\Contracts\TransferRequestRepositoryInterface;
use App\Repositories\Contracts\TripRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\VehicleTypeRepositoryInterface;
use App\Repositories\Eloquent\AddonRepository;
use App\Repositories\Eloquent\AiSuggestionRepository;
use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\BuildingRepository;
use App\Repositories\Eloquent\CommissionSettingRepository;
use App\Repositories\Eloquent\ContactMessageRepository;
use App\Repositories\Eloquent\CurrencyRepository;
use App\Repositories\Eloquent\DestinationRepository;
use App\Repositories\Eloquent\GoogleReviewRepository;
use App\Repositories\Eloquent\McpTokenRepository;
use App\Repositories\Eloquent\NationalityGroupRepository;
use App\Repositories\Eloquent\NewsletterSubscriberRepository;
use App\Repositories\Eloquent\PackageRepository;
use App\Repositories\Eloquent\PartnerRepository;
use App\Repositories\Eloquent\PaymentMethodRepository;
use App\Repositories\Eloquent\PaymentProofRepository;
use App\Repositories\Eloquent\PaymentRepository;
use App\Repositories\Eloquent\ProductPriceRepository;
use App\Repositories\Eloquent\RoomRepository;
use App\Repositories\Eloquent\SeasonPriceRepository;
use App\Repositories\Eloquent\SettingRepository;
use App\Repositories\Eloquent\TransferFixedRouteRepository;
use App\Repositories\Eloquent\TransferHubRepository;
use App\Repositories\Eloquent\TransferRequestRepository;
use App\Repositories\Eloquent\TripRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\VehicleTypeRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        AddonRepositoryInterface::class => AddonRepository::class,
        BookingRepositoryInterface::class => BookingRepository::class,
        BuildingRepositoryInterface::class => BuildingRepository::class,
        CurrencyRepositoryInterface::class => CurrencyRepository::class,
        DestinationRepositoryInterface::class => DestinationRepository::class,
        GoogleReviewRepositoryInterface::class => GoogleReviewRepository::class,
        PackageRepositoryInterface::class => PackageRepository::class,
        PartnerRepositoryInterface::class => PartnerRepository::class,
        RoomRepositoryInterface::class => RoomRepository::class,
        SeasonPriceRepositoryInterface::class => SeasonPriceRepository::class,
        SettingRepositoryInterface::class => SettingRepository::class,
        TripRepositoryInterface::class => TripRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        ContactMessageRepositoryInterface::class => ContactMessageRepository::class,
        NewsletterSubscriberRepositoryInterface::class => NewsletterSubscriberRepository::class,
        PaymentProofRepositoryInterface::class => PaymentProofRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        PaymentMethodRepositoryInterface::class => PaymentMethodRepository::class,
        TransferRequestRepositoryInterface::class => TransferRequestRepository::class,
        TransferFixedRouteRepositoryInterface::class => TransferFixedRouteRepository::class,
        VehicleTypeRepositoryInterface::class => VehicleTypeRepository::class,
        TransferHubRepositoryInterface::class => TransferHubRepository::class,
        CommissionSettingRepositoryInterface::class => CommissionSettingRepository::class,
        ProductPriceRepositoryInterface::class => ProductPriceRepository::class,
        NationalityGroupRepositoryInterface::class => NationalityGroupRepository::class,
        AiSuggestionRepositoryInterface::class => AiSuggestionRepository::class,
        McpTokenRepositoryInterface::class => McpTokenRepository::class,
    ];
}
