# Правки по проблемным ревизиям (Auto Flow QA)

Во всех шести ревизиях базовые поля имени и адреса (`DAC`, `DCS`, `DBB`, `DAG`, `DAI`, `DAK`) уже поддерживаются BarcodeGen. Проверка показала, что проблема шире нескольких неверных строк в YAML: BFF содержит старый слой изменения данных, а exact builders пяти ревизий сами превращают пустой `DAD` в `NONE`.

Главный контракт исправления:

> Пустое optional-поле остаётся пустым. BFF не заменяет пустоту строкой `NONE` и не придумывает пользовательские значения. `NONE` допустим только как **явно выбранное** значение закрытого списка.

## Архитектурное решение

Эти шесть ревизий должны работать по тому же Auto Flow, что и все остальные. Отдельную legacy-логику для них в BFF нужно удалить, не перенося её в новый flow.

## Где сейчас появляются скрытые значения

* Frontend вызывает `prepare`, получает `draftFields`, затем вызывает `generate` с `mode=prepared` (`app/pages/generating.vue`).
* `ChainExecutor.ExecuteGrouped` сначала нормализует semantic-поля, затем вызывает `ApplyEngineDefaults` до `generationSteps` (`internal/usecase/chain_executor.go:68-70`).
* `prepare` вызывает `applyChoiceFallbacks` после цепочки (`prepare_usecase.go:44-49`).
* `generate` повторно вызывает `applyChoiceFallbacks` до quote (`generate_usecase.go:161-169`), а затем повторно вызывает `ApplyEngineDefaults` перед render (`generate_usecase.go:320-322`).
* `ApplyEngineDefaults` считает `nil` и `""` отсутствующим значением. Поэтому явные `middleName=""`, `suffix=""` и `realId=""` могут быть перезаписаны.
* После BFF финальный payload собирает exact builder выбранной ревизии (`barcode-config.service.ts`). В CA, WA, CO, LA и OH шаблон содержит `data.DAD ? data.DAD : 'NONE'`; MI — единственная из шести ревизий, где пустой middle name уже обрабатывается без `NONE`.

Следовательно, недостаточно удалить несколько YAML-строк: нужно удалить старый слой автоподстановки целиком и отдельно исправить пять exact builders.

## Инвентаризация удаляемых defaults

Раздел `defaults` удаляется из этих BFF-профилей целиком, а не редактируется по одному полю. Ниже — инвентаризация данных, которые больше не должны материализоваться в BFF:

* **CA:** `DCA`, `DCB`, `DCD`, `DBC`, `DAY`, `DAZ`, `DAU`, `DAW`, `DAG`, `DAI`, `DAK`, `QQQ`.
* **WA:** `DAD`, `DCA`, `DCB`, `DCD`, `DBC`, `DAY`, `DAU`, `DAW`, `DAG`, `DAI`, `DAK`, `DDA`, `DDK`, `DDL`, `QQQ`.
* **LA:** `DAD`, `DCA`, `DCB`, `DCD`, `DBC`, `DAY`, `DAU`, `DAW`, `DAG`, `DAI`, `DAK`, `DDA`, `DDK`, `DDL`, `DCU`, `QQQ`, а также `ZLA`, `ZLB`, `ZLC`, `ZLE`, `ZLF`, `ZLI` со значением `NONE`. Последние особенно опасны: LA builder видит непустой `NONE` и уже не использует свои state-extension значения.
* **CO:** `DAD`, `DCA`, `DCB`, `DCD`, `DBC`, `DAY`, `DAZ`, `DAU`, `DAW`, `DAG`, `DAI`, `DAK`, `DDA`, `DCL`, `QQQ`.
* **MI:** `DAD`, `DAG`, `DAI`, `DAK`, `DBC`, `DDA`, `QQQ`; revision literal `DDB` переносится под ответственность BarcodeGen.
* **OH:** `DAD`, `DCA`, `DCB`, `DCD`, `DBC`, `DAY`, `DAZ`, `DAU`, `DAW`, `DAG`, `DAI`, `DAK`, `DDA`, `DDK`, `DDL`, `DCU`, `QQQ`.

Это убирает не только `DAD/DCU/DDK`, но и тестовые адреса, рост `510`, вес `150`, цвет `BRO`, автоматический REAL ID и неверные class/restriction/race значения.

## Что удалить и чем заменить

1. **Полностью удалить механизм defaults.** Удалить `ApplyEngineDefaults`, поле `defaults` из модели/парсера/валидатора профиля и сами YAML-блоки. Не заменять его `constants` внутри BFF: форматные константы должны задаваться BarcodeGen по revision key.
2. **Полностью удалить runtime fallback-ы.** Удалить `applyChoiceFallbacks` и запретить `prepare`/`generate` дописывать `fallbackValue` в request/draft. Начальное значение select — только UI-состояние, которое frontend явно отправляет. Для required closed-choice отсутствие ключа возвращает validation error; для optional closed-choice пустая строка сохраняется.
3. **Требовать явную отправку выбранных списков.** `DCB` и `DCD` могут иметь вариант `NONE`, а WA `DCA` по проверенному контракту имеет единственный вариант `NONE`. Это значения select, а не подстановка вместо отсутствующего поля. CO `DCL` не имеет варианта `NONE`.
4. **Сначала normalize, потом filter.** Пока semantic → AAMVA mapping остаётся в BFF, выполнять `NormalizeEngineFields(resolvedFields)` до `filterRenderValues`. Текущий порядок удаляет `firstName`, `lastName`, `street`, `city`, `zipCode` до их преобразования. В целевой архитектуре mapping также можно перенести в единый контракт BarcodeGen.
5. **Не продолжать после ошибки `prepare`.** Frontend не должен отправлять semantic-поля как `mode=prepared`, если `prepare` завершился ошибкой.
6. **Заменить скрытые значения обязательностью.** Поля, которые exact builder безусловно читает или форматирует (`padStart`, `padEnd`, `toString`), должны быть required в schema/`requiredRenderFields`. Отсутствие поля — явная 4xx ошибка до billing/render, а не тестовое значение.
7. **Не показывать generated-поля как начальный ввод.** `DCJ` для WA и CO появляется после `prepare`; LA `DCJ` остаётся ручным обязательным полем.
8. **Нормализовать boolean строго по типу.** `true → "1"`, `false/отсутствие → поле не передавать`. Строки `"false"` и `"NONE"` должны отклоняться, а не считаться truthy. Для OH builder также нужно не печатать пустые `DDK`/`DDL` теги при `false`.
9. **Добавить alias `heightInches → DAU`.** Пока mapping остаётся в BFF, он должен знать это поле; после переноса контракта alias должен жить в BarcodeGen.
10. **Оставить только общую orchestration.** В BFF не должно быть state-specific defaults, вычислений, compatibility fallback-ов или исправления payload. Новый общий `ChainExecutor` и декларативные `generationSteps` можно оставить: они выбирают операции BarcodeGen, но не реализуют их и не придумывают данные.

## Точечные правки BarcodeGen, без которых `DADNONE` останется

* В CA (`barcode-config.service.ts:174-175`), WA (`:3124`), CO (`:3714`), LA (`:4360`) и OH (`:4668`) заменить `DAD${data.DAD ? data.DAD : 'NONE'}` на сохранение пустого payload, например `DAD${data.DAD ?? ''}`. Не вставлять литерал `NONE`.
* MI builder уже корректен: `DCT${data.DAC}${data.DAD ? ' ' + data.DAD : ''}`.
* В CO state-extension (`:3716`) не использовать `data.DCB ? data.DCB : 'NONE'`. `DCB` должен прийти как явный выбор `NONE` или `C`; если выбора нет, запрос должен быть отклонён до builder-а.
* Select options `NONE` в `DCB`/`DCD` не удалять. Для WA также сохранить единственный явный option `DCA=NONE`.

---

## 1. Ohio (`US_OH_07012018`)

* **Логика веса (`DAW` / `DAX`):** оставить новой логике BarcodeGen. Пользователь вводит `DAW` в фунтах, `DAX` вычисляется автоматически как `Math.round(DAW * 0.45359237)`.
* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `suffix` (`DCU`) — `""`, `JR`, `SR`, `1ST`..`9TH`;
  * `sex` (`DBC`) — `1` | `2`, н��чальное значение формы `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `hairColor` (`DAZ`);
  * `vehicleClass` (`DCA`) — только `D`, начальное значение формы `D`;
  * `restrictions` (`DCB`) — `A` | `B`, начальное значение формы `A`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, начальное значение формы `F`;
  * `organDonor` (`DDK`) и `veteran` (`DDL`) — boolean без строкового default.
* **Вырезать в BFF:**
  * defaults `DAD="NONE"`, `DCU="NONE"`, `DCA="C"`, `DCB="NONE"`, `DAU="510"`, `DAW="150"`, `DDK="NONE"`, `DDL="NONE"`;
  * любую отдельную BFF-логику расчёта `DAX` — это уже делает internal BarcodeGen.

---

## 2. Washington (`US_WA_11122019`)

* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `sex` (`DBC`) — `1` | `2`, начальное значение формы `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `vehicleClass` (`DCA`) — `NONE`;
  * `restrictions` (`DCB`) — `NONE` | `B`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, начальное значение формы `F`;
  * `organDonor` (`DDK`) и `veteran` (`DDL`) — boolean без строкового default.
* **Проверено:** автогенерация уже реализована. Если пользователь не передал свой `DCJ`, общий `prepare` выполняет:
  * `random[DAJ, DBD] → DCJ`;
  * `calculate[DAJ, DAQ, DCJ] → DCF`.

  Переписывать эту логику не нужно — нужно дать ей полностью отработать.
* **Вырезать блокеры в BFF:**
  * убрать `auditCode/DCJ` из обязательных полей начальной формы; `requiredRenderFields: [DCJ]` оставить, потому что после `prepare` поле уже должно быть заполнено;
  * удалить шаг `DCK` из `generationSteps` — WA builder его не использует;
  * удалить defaults `DAD="NONE"`, `DCA="C"`, `DAU="510"`, `DAW="150"`, `DDK="NONE"`, `DDL="NONE"`.
* **По сломанному `DCS` и адресу:** в WA-builder переносы между `DCS`, `DAG`, `DAI`, `DAJ` и `DAK` уже стоят правильно. Вероятная причина находится в fallback-пути BFF: frontend проглатывает ошибку `prepare` и отправляет semantic-поля как `prepared`, а BFF применяет `renderAllowlist` раньше `NormalizeEngineFields`. Из-за этого `lastName`, `street`, `city` и `zipCode` могут быть отброшены до преобразования в `DCS`, `DAG`, `DAI`, `DAK`.

  Исправление: выполнять `NormalizeEngineFields` до `renderAllowlist` и не продолжать `prepared`-генерацию после ошибки `prepare`. Отдельный WA-костыль для переносов не нужен.

---

## 3. Louisiana (`US_LA_02102015`)

* **Поле `auditCode` (`DCJ`):** оставить обязательным ручным вводом. Для LA BarcodeGen не генерирует его автоматически. Пустая строка и `NONE` должны отклоняться валидацией.
* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `suffix` (`DCU`) — `""`, `JR`, `SR`, `1ST`..`9TH`;
  * `sex` (`DBC`) — `1` | `2`, начальное значение формы `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `vehicleClass` (`DCA`) — `D` | `C` | `E`, начальное значение формы `D`;
  * `restrictions` (`DCB`) — `NONE` | `C` | `01`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, начальное значение формы `F`;
  * `organDonor` (`DDK`) и `veteran` (`DDL`) — boolean без строкового default.
* **Вырезать в BFF:** defaults `DAD="NONE"`, `DCU="NONE"`, `DCA="C"`, `DAU="510"`, `DAW="150"`, `DDK="NONE"`, `DDL="NONE"`.
* **Точечная правка:** проверять не только отсутствие `DCJ`, но и запрещённое значение `NONE`.

---

## 4. California DL (`US_CA_08292017`)

В California ID ничего менять не нужно — правки относятся только к DL.

* **Убрать лишние поля:** `Signature Image URL` и `Photo Image URL` из профильной формы. Они не относятся к AAMVA Auto Flow.
* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `suffix` (`DCU`) — `""`, `JR`, `SR`, `1ST`..`9TH`;
  * `sex` (`DBC`) — `1` | `2`, нач��льное значение формы `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `hairColor` (`DAZ`);
  * `vehicleClass` (`DCA`) — `A` | `C`, начальное значение формы `C`;
  * `restrictions` (`DCB`) — `NONE` | `01`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, начальное значение формы `F`;
  * `organDonor` (`DDK`) и `veteran` (`DDL`) — boolean без строкового default.
* **Вырезать в BFF:** defaults `DAD="NONE"`, `DCU="NONE"`, `DDK="NONE"`, `DDL="NONE"` и старые поля изображения из schema.
* **Точечная правка:** добавить `heightInches → DAU`, иначе новое поле не попадёт в builder.

---

## 5. Colorado 2015 (`US_CO_10302015`)

* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `sex` (`DBC`) — `1` | `2`, начальное значение формы `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `hairColor` (`DAZ`);
  * `race` (`DCL`) — `AI`, `AP`, `BK`, `H`, `O`, `W`, `U`, начальное значение формы `U`;
  * `vehicleClass` (`DCA`) — `R` | `C`, начальное значение формы `R`;
  * `restrictions` (`DCB`) — `NONE` | `C`;
  * `endorsements` (`DCD`) — `NONE` | `M`;
  * `realId` (`DDA`) — `""` | `N` | `F`, начальное значение формы `F`. Значение участвует в генерации `DBD + DBA`.
* **Оставить новой Auto Flow-логике цепочку:** `DAQ → DBD + DBA → DCF → DCJ` в порядке, заданном рабочей логикой BarcodeGen.
* **Вырезать в BFF:**
  * обязательный ручной ввод `auditCode/DCJ` на начальной форме;
  * шаг `DCK` — CO builder его не использует;
  * defaults `DAD="NONE"`, `DCA="C"`, `DCL="NONE"`, `DAU="510"`, `DAW="150"`.
* Поля `DDK`, `DDL` и `DCU` для CO не добавлять.

---

## 6. Michigan 2011 (`US_MI_Rev_01-21-2011`)

* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима и участвует в `DAQ`/`DCT`;
  * `sex` (`DBC`) — `1` | `2`, начальное значение формы `1`;
  * `realId` (`DDA`) — `""` | `N` | `F`, начальное значение формы `F`.
* Физические поля, class/restrictions/endorsements, donor и veteran не добавлять: MI builder их не использует.
* **Проверено:** отдельную новую логику для MI писать не нужно. BarcodeGen уже содержит MI builder и нужные генераторы. Цепочка должна быть:
  * literal `DDB="Rev 01-21-2011"` — exact resolver и internal DTO уже поддерживают это значение;
  * `random[DAJ, DAC, DAD, DCS, DBB] → DAQ`;
  * `random[DAJ, DBB, DDB, DDA] → DBD + DBA`;
  * `calculate[DAJ, DAQ, DBB, DBA] → DCK`;
  * отдельный `DCF` не вызывать — MI builder его не использует, и в текущем профиле такого шага уже нет.
* **Вырезать блокеры в BFF:**
  * удалить default `DAD="NONE"`, иначе builder формирует `DCT<first> NONE`, а `DAQ` рассчитывается с искусственным middle name;
  * заменить сокращённый шаг `DAQ` с input `[DAJ]` на `[DAJ, DAC, DAD, DCS, DBB]`;
  * передавать в шаг дат полный набор `[DAJ, DBB, DDB, DDA]`;
  * убрать `DBD` из MI input для `DCK`.
* **Одна точечная правка в BarcodeGen:** общий guard операции `DCK` сейчас требует `DBD` для всех штатов, хотя MI helper использует только `DAQ`, `DBB` и `DBA`. Сделать этот guard profile-aware для MI. После этого новой общей Auto Flow-логике достаточно дать выполнить цепочку без дополнительных MI-костылей.

---

## Ожидаемый результат

Эти шесть ревизий работают по стандартному Auto Flow, который уже используется всеми остальными ревизиями, без отдельной legacy-логики в BFF.

Отдельные исключения остаются только там, где этого действительно требует builder:

* LA — ручной `DCJ`;
* WA и CO — автоматический `DCJ`, без `DCK`;
* OH — автоматический `DAX`;
* MI — literal revision и собственный набор входов для `DAQ`/`DCK`;
* все штаты — корректная семантика пустых optional-полей и boolean-флагов.

---

## Дополнительные баги в новой Auto Flow-логике

Ниже — отдельные дефекты, найденные при проверке новой реализации. Они не повторяют уже описанные ошибки с порядком `NormalizeEngineFields` / `filterRenderValues` и проглатыванием ошибки `prepare`.

### 1. `ChainExecutor` всё ещё может перезаписать пользовательское значение

`ExecuteGrouped` создаёт `resolved = NormalizeEngineFields(baseInput)`, но при обработке output проверяет `hasUserValue(baseInput, field)` по исходной, ненормализованной карте (`chain_executor.go:68, 87-96`). Поэтому semantic-ввод не считается пользовательским значением engine-поля.

Пример: пользователь передал `auditCode`, нормализация создала `DCJ`, но проверка ищет `DCJ` в исходном `baseInput` и не находит. Шаг `audit` затем бесшумно заменяет пользовательский код сгенерированным. Та же ошибка применима к `issueDate → DBD`, `expiryDate → DBA`, `documentDiscriminator → DCF` и другим alias-полям, если они являются output шага.

**Исправление:** один раз создать нормализованный `userInput` до defaults и использовать его и для `resolved`, и для проверки `Skipped`. Если одновременно пришли semantic- и engine-ключ с разными непустыми значениями, не выбирать один молча, а возвращать conflict/validation error.

### 2. `generatedFields` и имена полей формы находятся в разных пространствах имён

`RevisionConfig.GeneratedFields()` возвращает engine-коды из `generationSteps.output`, например `DCJ`, а frontend скрывает поле через `generated.has(field.name)`, где `field.name` равен `auditCode` (`revision_config.go:63-74`, `generating.vue:92-97`). Поэтому `DCJ !== auditCode`: generated-поле WA/CO остаётся на начальной форме.

Вместе с багом выше это даёт особенно опасный сценарий: UI показывает `auditCode`, пользователь его вводит, а `ChainExecutor` всё равно генерирует новый `DCJ` и перезаписывает ввод.

**Исправление:** отдавать в schema generated-признак в том же namespace, что и `FieldSchema.Name`, предпочтительно явным `source: generated`. Альтернатива — единый публичный alias-registry, используемый и BFF, и frontend; локальные несвязанные списки недопустимы.

### 3. Частичный override пары дат создаёт непроверенную смешанную пару

`runStep` сначала генерирует и валидирует полную пару `[DBD, DBA]`, а только потом `ExecuteGrouped` отдельно пропускает каждый output, который считает пользовательским (`chain_executor.go:79-98, 105-137`). Если пользователь передал только `DBD` или только `DBA`, финальный draft собирается из одной пользовательской и одной сгенерированной даты. Валидатор проверял другую — полностью сгенерированную — пару.

В `auto` финальная смешанная пара больше не проверяется: `validatePreparedDates` вызывается только для точного `mode=prepared` (`generate_usecase.go:329-334`). В результате expiration может оказаться не позже фактического issue date.

**Исправление:** считать date-step атомарным. Либо пользователь передаёт обе даты, и BFF валидирует именно их, либо BarcodeGen генерирует обе. Частичный override должен быть отклонён. Финальную пару нужно валидировать во всех режимах перед Billing.

### 4. Native `Derive` теряет revision и нарушает атомарность grouped-step

`HTTPClient.Derive(ctx, revision, ...)` игнорирует аргумент `revision` и вызывает `Random/Calculate` с пустой строкой (`http_client.go:176-198`). При этом `native` — режим по умолчанию (`config.go:174`). Выбор state/revision в BarcodeGen становится невозможным или зависит от случайного содержимого input.

Кроме того, native `Derive` выполняет отдельный HTTP-вызов для каждого output. Значит, заявленный grouped date-step `[DBD, DBA]` фактически берёт `DBD` из одного random-вызова, а `DBA` из другого. Валидатор может принять календарно допустимую, но не атомарно сгенерированную пару.

**Исправление:** как минимум передавать реальный `revision` в каждый вызов. Для `[DBD, DBA]` использовать одну grouped-ручку BarcodeGen; если native-контракт её не поддерживает, этот adapter не должен использоваться для Auto Flow как эквивалент grouped derive.

### 5. `supportedModes` не применяется к `generate`

`prepare` проверяет capability через `supportsMode`, а `GenerateUseCase.Execute` — нет. Любая строка, кроме точного `"prepared"`, считается auto (`generate_usecase.go:223`), а финальная проверка prepared-дат выполняется только при точном совпадении (`:330`).

Следствия:

* опечатка вроде `preapred` неожиданно запускает derive-цепочку;
* LA и MI принимают `mode=auto`, хотя их профили объявляют только `[prepare, prepared]`;
* неизвестный mode обходит prepared-only ветку валидации.

**Исправление:** до quote разрешать только `auto` и `prepared`, затем проверять выбранный mode по `cfg.SupportedModes`. Пустой mode можно явно нормализовать в `auto`, но не трактовать любую неизвестную строку как auto.

### 6. `schema.fields[].required` не является серверной обязательностью

BFF проверяет только `requiredInputFields` и `requiredRenderFields`; флаг `FieldSchema.Required` нигде не участвует в серверной валидации (`generate_usecase.go:899-913`). Например, в текущем CA-профиле `eyeColor` отмечен `required: true`, но отсутствует и в `requiredInputFields`, и в `requiredRenderFields`. Сейчас это маскирует default `DAY=BRO`; после удаления defaults поле сможет исчезнуть без явного 4xx и дойти до builder-а пустым/`undefined`.

**Исправление:** профиль должен иметь один исполняемый required-контракт. После normalize и до quote/Billing BFF обязан проверить все обязательные пользовательские и render-поля. UI-флаг `required` не должен расходиться с runtime-валидацией.

### 7. Billing блокируется до финальной валидации payload

`Billing.Block` выполняется на `generate_usecase.go:298-309`, а `NormalizeEngineFields`, `requiredRenderFields`, проверка prepared-дат и `applyRenderTransforms` — только после него (`:314-340`). При любой из этих ошибок usecase сразу возвращается и не вызывает `Billing.Release`; компенсация начинается только после попыток render (`:357-369`).

Это особенно проявляется в уже найденном fallback-сценарии: `prepare` упал, frontend отправил неполный `prepared`, BFF заблокировал средства и лишь затем обнаружил отсутствующий `DCJ` или даты.

**Исправление:** все детерминированные преобразования и проверки выполнять до `Billing.Block`. Дополнительно после успешного Block должен быть единый compensating guard, который делает Release при любом раннем выходе до Capture.

### 8. Frontend удаляет явно пустые optional-поля ещё до `prepare`

`buildFields()` пропускает любое значение `raw === ""` (`generating.vue:154-161`). Поэтому `middleName=""`, `suffix=""`, `realId=""` не пересекают границу frontend → BFF как осознанный пользовательский выбор. BFF не может отличить «пользователь оставил пустым» от «поле не пришло» и применяет default/fallback.

**Исправление:** передавать явную пустую строку для optional string/enum, где пустота является допустимым бизнес-значением. Состояния «не трогал» и «явно очистил» должны различаться контрактом, а не схлопываться удалением ключа.

### 9. Frontend не задаёт `buildId`/`batchId`, хотя BFF строит из них billing saga

`generating.vue` вызывает `service.generate` без `buildId` и `batchId`; service отправляет их как `undefined`, то есть ключи исчезают из JSON (`barcodeGeneration.service.ts:120-128`). BFF не требует эти поля и строит `sagaID = saga-<userID>-<buildID>-<batchID>` (`generate_usecase.go:242-246`). Для всех обычных генераций одного пользователя получается одинаковый `saga-<userID>--`.

Следствие — независимые генерации могут конфликтовать в Billing и попадать в одну business saga, несмотря на разные `X-Idempotency-Key`.

**Исправление:** frontend должен создавать стабильные `buildId` и `batchId` один раз на пользовательское действие. BFF обязан либо требовать оба идентификатора, либо детерминированно выводить отсутствующий ID из `userID + X-Idempotency-Key`; пустые идентификаторы нельзя допускать до Block.
