# v5.0.0
This version adapts to latest api v5 changes.
See API docs: https://panel.rtbhouse.com/api/docs for details.

For now, three methods - `getRbStats` (for RTB only), `getDpaStats` (for DPA only) and `getSummaryStats` (for RTB + DPA) shares similar parameters and output:
```
get(Rtb|Dpa|Summary)Stats(
    advHash,  # Advertiser hash. No changes.
    dayFrom,  # Date range start (inclusive). No changes for RTB. For DPA this parameter is now obligatory (was not in the past).
    dayTo,  # Date range end (inclusive). No changes for RTB. For DPA this parameter is now obligatory (was not in the past).
    groupBy,  # Array of grouping columns. Refer to api docs for list of possible values. No changes for RTB. For DPA this now accepts array instead of single value.
    metrics,  # Array of value columns. Refer to api docs for list of possible values. This parameter was newly added.
    countConvention,  # (Optional) Conversions counting convention. Changes: Defaults to null; This parameter must only be set if at least one conversions related metric is selected.
    subcampaigns,  # (Optional) Subcampaigns filter. No changes.
    userSegments,  # (Optional, RTB only) User segments filter. No changes.
    deviceTypes,  # (Optional, RTB only) Device types filter. No changes.
    placement,  # (Optional, DPA only). Placement filter. No changes.
) -> [[
    "grouping field 1 name" => "grouping field 1 value 1",  # No changes
    "grouping field N name" => "grouping field N value 1",  # No changes
    "grouping field X details" => "grouping field X details values",  # No changes
    "metric 1 name" => "metric field 1 value",  # Changes: now only metrics requested by `metrics` parameter are returned
]]
```

`getDpaCampaignStats` was removed, use `getDpaStats` instead.

`includeDpa` in `getRtbStats` is no longer supported, use `getSummaryStats` instead.

A few new metrics were added, refer to docs (as above) for details.

A few metrics changed their names. `ecc` was renamed to `ecpa`, `cpc` was renamed to `ecpc`.

`countConvention` parameter is now not needed if no conversions related metrics are requested.

# v4.0.0
This version adapts to latest api v4 changes.

`getRtbCreatives` now provides faster results with different output:
Refer to `https://panel.rtbhouse.com/api/docs` - `GET /advertisers/{hash}/rtb-creatives` for details

# v3.0.0
This version adapts to latest api v3. changes.

Multiple stats loading functions: `getRtbCreativeCountryStats`, `getRtbCountryStats`, `getRtbDeviceStats`, `getRtbCreativeStats`, `getRtbCategoryStats`, `getCampaignStatsTotal`, `getRtbCampaignStats` are now replaced with single `getRtbStats` method, see below.
- `campaign` in `groupBy` is renamed to `subcampaign`.
- `categoryId` grouping is renamed to `category`. In output `categoryId` is removed, `category` now contains category identifier (previously name) and new field `categoryName` is added.
- `creativeId` grouping is renamed to `creative`. In output `hash` is renamed to `creative`. All creative details are prefixed with `creative` (`creativeName`, `creativeWidth`, `creativeCreatedAt`).
- `conversionsRate` in output is renamed to `cr`.
- Indeterminate values (ex. ctr when there are no imps and clicks) are now `null`, previously `0`.

For example:
- `getRtbCampaignStats` equals to `getRtbStats`, with default `groupBy` set to `['day']`.
- `getCampaignStatsTotal` equals to `getRtbStats`, with default `groupBy` set to `['day']` and `includeDpa` set to `true`.
- `getRtbCategoryStats` equals to `getRtbStats` with `groupBy` set to `['category']`.
- `getRtbCreativeStats` equals to `getRtbStats` with `groupBy` set to `['creative']`.
- `getRtbDeviceStats` equals to `getRtbStats` with `groupBy` set to `['deviceType']`.
- `getRtbCountryStats` equals to `getRtbStats` with `groupBy` set to `['country']`.
- `getRtbCreativeCountryStats` equals to `getRtbStats` with `groupBy` set to `['creative', 'country']`.
