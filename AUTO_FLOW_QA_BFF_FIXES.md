# Правки по проблемным ревизиям (Auto Flow QA)

Во всех перечисленных ниже штатах базовые поля имени и адреса (`DAC`, `DCS`, `DBB`, `DAG`, `DAI`, `DAK`) уже поддерживаются BarcodeGen.

Основная проблема находится не в builder-ах BarcodeGen, а в старых правилах BFF для этих профилей: неполных схемах, неверных defaults, лишних шагах цепочки и фильтрации полей. Для этих штатов нужно убрать старые ограничения BFF и дать отработать новой общей Auto Flow-логике так же, как она работает для остальных ревизий.

## Как сейчас работает новая логика

* Frontend вызывает `prepare`, получает `draftFields`, затем вызывает `generate` с `mode=prepared` (`app/pages/generating.vue`).
* BFF запускает общий `ChainExecutor.ExecuteGrouped`: нормализует semantic-поля в AAMVA-коды и выполняет `generationSteps` профиля (`internal/usecase/prepare_usecase.go`, `chain_executor.go`). Старый `CalculationChain` в runtime уже не используется.
* BFF вызывает internal `random`, `calculate` и `pdf417` BarcodeGen. Финальный payload собирает exact builder выбранной ревизии (`src/barcode/barcode.service.ts`, `barcode-config.service.ts`).

То есть целиком удалять профиль из BFF нельзя: новой общей логике всё ещё нужны корректные `generationSteps`, константы ревизии и обязательные render-поля. Нужно удалить только старые правила, которые меняют или блокируют корректные данные.

## Что убрать или исправить в BFF для всех 6 профилей

* Удалить `DAD="NONE"` из defaults OH, WA, LA, CO и MI. Пустой `middleName` должен дойти до BarcodeGen пустым:
  * обычные builders сами решают, писать ли `DADNONE`;
  * в MI пустой `DAD` не добавляется в `DCT`, а BFF-default сейчас ошибочно даёт `DCT<first> NONE` и участвует в расчёте `DAQ`.
* Удалить `DCU="NONE"` в OH и LA. Иначе непустая строка приводит к реальному полю `DCUNONE`; отсутствие suffix должно передаваться пустым.
* Удалить `DDK="NONE"` и `DDL="NONE"` в OH, WA и LA. Перед BarcodeGen флаги нормализовать как `true → "1"`, `false → поле отсутствует`. Строки `"false"` и `"NONE"` truthy и ошибочно включают donor/veteran.
* Удалить фиктивные defaults адреса `DAG="1 MAIN ST"`, `DAI="CITY"/"LOS ANGELES"`, `DAK="00000"/"90001"`. Если адрес обязателен для builder-а, сделать `street`, `city` и `zipCode` обязательными в форме, а не подменять их тестовыми значениями.
* Удалить фиктивные `DAU="510"` и `DAW="150"`, если значения должны приходить из формы. `DAU="510"` особенно неверен при формате total inches: 5′10″ должно передаваться как `70`, после чего builder сформирует `070 in`.
* Не перезаписывать явно выбранный `realId=""` значением `DDA="F"`. Сейчас и `ApplyEngineDefaults`, и choice fallback считают пустую строку отсутствующим значением. Для No Mark нужно сохранить явную пустую строку до builder-а.
* `NONE` не удалять вслепую: для `DCB`, `DCD` и LA state-specific полей это может быть корректный литерал. Убирать нужно только неверные defaults конкретной ревизии (`DCA`, `DCB`, `DCD`, `DCL` перечислены ниже).
* Не показывать generated-поля как пользовательский ввод. В частности, `DCJ` для WA и CO должен появляться после `prepare`, а не как `Audit Information *` на начальной форме.
* Сначала выполнять semantic → engine normalization, затем `renderAllowlist`. Сейчас BFF сначала фильтрует поля и только потом маппит `firstName → DAC`, `lastName → DCS`, `street → DAG` и т. д. Из-за этого semantic-поля могут быть отброшены до маппинга.
* Добавить alias `heightInches → DAU`. Сейчас BFF знает только `height → DAU`.

---

## 1. Ohio (`US_OH_07012018`)

* **Логика веса (`DAW` / `DAX`):** оставить новой логике BarcodeGen. Пользователь вводит `DAW` в фунтах, `DAX` вычисляется автоматически как `Math.round(DAW * 0.45359237)`.
* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `suffix` (`DCU`) — `""`, `JR`, `SR`, `1ST`..`9TH`;
  * `sex` (`DBC`) — `1` | `2`, fallback `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `hairColor` (`DAZ`);
  * `vehicleClass` (`DCA`) — только `D`, fallback `D`;
  * `restrictions` (`DCB`) — `A` | `B`, fallback `A`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, fallback `F`;
  * `organDonor` (`DDK`) и `veteran` (`DDL`) — boolean без строкового default.
* **Вырезать в BFF:**
  * defaults `DAD="NONE"`, `DCU="NONE"`, `DCA="C"`, `DCB="NONE"`, `DAU="510"`, `DAW="150"`, `DDK="NONE"`, `DDL="NONE"`;
  * любую отдельную BFF-логику расчёта `DAX` — это уже делает internal BarcodeGen.

---

## 2. Washington (`US_WA_11122019`)

* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `sex` (`DBC`) — `1` | `2`, fallback `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `vehicleClass` (`DCA`) — `NONE`;
  * `restrictions` (`DCB`) — `NONE` | `B`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, fallback `F`;
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
  * `sex` (`DBC`) — `1` | `2`, fallback `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `vehicleClass` (`DCA`) — `D` | `C` | `E`, fallback `D`;
  * `restrictions` (`DCB`) — `NONE` | `C` | `01`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, fallback `F`;
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
  * `sex` (`DBC`) — `1` | `2`, fallback `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `hairColor` (`DAZ`);
  * `vehicleClass` (`DCA`) — `A` | `C`, fallback `C`;
  * `restrictions` (`DCB`) — `NONE` | `01`;
  * `endorsements` (`DCD`) — `NONE`;
  * `realId` (`DDA`) — `""` | `N` | `F`, fallback `F`;
  * `organDonor` (`DDK`) и `veteran` (`DDL`) — boolean без строкового default.
* **Вырезать в BFF:** defaults `DAD="NONE"`, `DCU="NONE"`, `DDK="NONE"`, `DDL="NONE"` и старые поля изображения из schema.
* **Точечная правка:** добавить `heightInches → DAU`, иначе новое поле не попадёт в builder.

---

## 5. Colorado 2015 (`US_CO_10302015`)

* **Добавить в форму и маппинг:**
  * `middleName` (`DAD`) — опциональная пустая строка допустима;
  * `sex` (`DBC`) — `1` | `2`, fallback `1`;
  * `heightInches` (`DAU`);
  * `weightPounds` (`DAW`);
  * `eyeColor` (`DAY`);
  * `hairColor` (`DAZ`);
  * `race` (`DCL`) — `AI`, `AP`, `BK`, `H`, `O`, `W`, `U`, fallback `U`;
  * `vehicleClass` (`DCA`) — `R` | `C`, fallback `R`;
  * `restrictions` (`DCB`) — `NONE` | `C`;
  * `endorsements` (`DCD`) — `NONE` | `M`;
  * `realId` (`DDA`) — `""` | `N` | `F`, fallback `F`. Значение участвует в генерации `DBD + DBA`.
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
  * `sex` (`DBC`) — `1` | `2`, fallback `1`;
  * `realId` (`DDA`) — `""` | `N` | `F`, fallback `F`.
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

После удаления конфликтующих state-specific правил BFF эти шесть ревизий должны проходить через тот же общий Auto Flow, что и рабочие профили:

`schema → prepare/ExecuteGrouped → prepared draft → exact BarcodeGen builder → render`.

Отдельные исключения остаются только там, где этого действительно требует builder:

* LA — ручной `DCJ`;
* WA и CO — автоматический `DCJ`, без `DCK`;
* OH — автоматический `DAX`;
* MI — literal revision и собственный набор входов для `DAQ`/`DCK`;
* все штаты — корректная семантика пустых optional-полей и boolean-флагов.
