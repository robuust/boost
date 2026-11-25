# Seeding examples

Use these examples to match the host project's existing seeding style before introducing a new one. They are adapted from production projects; model and field names are illustrative.

## Relationship-first parent graph

```php
Project::factory([
    'type' => ProjectType::Calculation,
    'name' => 'New detached house',
])->has(
    Calculation::factory()
        ->count(3)
        ->has(
            CalculationItem::factory()
                ->count($count)
                ->seedFromLibraryForCalculation($count),
            'items'
        ),
    'calculations'
)->has(
    WorkOrder::factory()
        ->count(1)
        ->state([
            'name' => 'Prepare building site',
            'status' => WorkOrderStatus::ToSchedule,
        ])
        ->for($adminUser, 'creator'),
    'workOrders'
)->create();
```

Use this when the seeded object graph is naturally owned by one parent model and can be expressed in one readable factory chain.

## Parameterized sub-seeders

```php
$spaces = $this->call(SpaceSeeder::class);
$products = $this->callWith(ProductSeeder::class, [
    'bedrooms' => $spaces['bedrooms'],
    'rooms' => $spaces['rooms'],
]);

$this->callWith(BookingSeeder::class, [
    'clients' => $clients['company'],
    'rooms' => $spaces['rooms'],
    'other' => $products['other'],
    'users' => $users,
]);
```

```php
Booking::factory()
    ->count(11)
    ->state(fn () => [
        'client_id' => $clients->random()->id,
    ])
    ->has(
        BookingLine::factory(['sequence' => 6])
            ->for(
                BookingLineProduct::factory()
                    ->withReservation($rooms->random()->space)
                    ->for($other),
                'bookable'
            ),
        'lines'
    )
    ->create();
```

Use this when the project already seeds domain data in stages and later seeders need concrete seeded collections or records from earlier steps.

## Attached relations with pivot data

```php
$companies->push(Company::factory()
    ->withMainUser($user)
    ->hasAttached(
        Member::factory()->count(1),
        ['member_no' => fake()->unique()->randomNumber(6)]
    )
    ->has(User::factory()->count(3)->withRole('Company'))
    ->create());
```

```php
$member = Member::factory()
    ->hasAttached(
        $company,
        ['member_no' => fake()->unique()->randomNumber(6)]
    )
    ->has(Meeting::factory()->withAgenda()->for($company))
    ->withFailedExam($oldCourse)
    ->withPassedExam($course)
    ->create();
```

Use this when the important part of the seed is a pivot-backed relationship, not just a plain child record.

## Domain seeder plus post-create enrichment

```php
$this->call([
    ShipmentScopesSeeder::class,
]);

Client::factory()
    ->count(5)
    ->has(User::factory()->withShipmentRequest()->withRole('Client admin'))
    ->withAddress(2)
    ->withDocuments()
    ->create()
    ->each(function ($client) use ($relations) {
        $client->debtors()->create([
            'external_relation_id' => $relations->random(),
        ]);
    });
```

Use this when the main domain records can be seeded cleanly with factories, but a small follow-up step is still needed to connect them to already-seeded external or imported data.

Keep the post-create step narrow. If the relation can be expressed with `->for(...)`, `->has(...)`, or `->hasAttached(...)`, prefer that instead.

## Explicit scenario records

```php
$this->callWith(OrderSeeder::class, [
    'id' => $orderId,
    'user' => $salesUser->id,
]);

$this->callWith(TaskSeeder::class, [
    'customWorkOrder' => $scenarioOrder,
    'user' => $salesUser,
]);
```

```php
$salesOrder = SalesOrder::factory()->create([
    'order_number' => $orderNumber,
    'customer_name' => $customerName,
    'sales_person_id' => $user,
]);

StockMutation::factory()
    ->has($lines, 'lines')
    ->for($salesOrder, 'internalSource')
    ->create([
        'description' => "Stock allocation #{$orderNumber}",
    ]);
```

Use this when the app depends on recognizable seeded scenarios with fixed identifiers, explicit statuses, or named records that support manual QA and demos.
