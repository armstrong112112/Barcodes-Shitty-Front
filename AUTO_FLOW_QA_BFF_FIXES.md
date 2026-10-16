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
  * `sex` (`DBC`) — `1` | `2`, начальное значение формы `1`;
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
