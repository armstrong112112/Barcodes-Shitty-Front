# ТЗ: основной Prepared / Regular flow генерации баркода

## 1. Коротко

Этот flow — нормально сделанный `prepared`, а не отдельный третий режим.

Пользователь:

1. выбирает страну, штат, ревизию и тип документа;
2. получает полную semantic-форму выбранного exact-профиля;
3. вручную заполняет базовые данные человека;
4. при желании нажимает `Random` или `Calculate` только возле разрешённого generated/calculated поля;
5. может исправить любой полученный результат;
6. нажимает финальную кнопку;
7. BFF проверяет полный semantic payload, внутри маппит его в AAMVA и вызывает render.

Главное отличие от Auto Flow:

- Auto сам выполняет всю последовательность действий;
- Prepared хранит те же короткие действия, но выполняет только одно действие после явного клика пользователя;
- результат одного действия может разблокировать следующую кнопку, но не запускает её;
- финальная кнопка Prepared не вызывает `random`, `calculate`, `prepare` или `ChainExecutor`.

```text
Auto:
base fields → BFF сам выполняет action 1 → action 2 → action 3 → render

Prepared:
полная semantic-форма
  → пользователь нажал Random у documentNumber
  → пользователь нажал Random у issueDate+expirationDate
  → пользователь нажал Calculate у inventoryNumber
  → пользователь всё проверил и отредактировал
  → BFF маппит draft в engine fields и делает render без скрытых actions
```

## 2. Откуда берётся логика

### 2.1. Что именно делал старый PHP frontend

Старый PHP frontend — только **поведенческий референс**. Новый frontend не должен открывать его страницы, вызывать его PHP-файлы или повторять его внутренний формат запросов.

Нужный нам сценарий находился в ветке `Regular` файла `barcodes/stateGet.php` и работал так.

#### Шаг 1. Пользователь выбирал exact-профиль

До показа формы пользователь выбирал страну, штат, дату ревизии и тип документа. PHP сохранял эти значения в скрытые поля:

- `DAJ` — штат;
- `DDB` — дата ревизии;
- `QQQ` — тип документа;
- `DCG` — страна.

После этого `stateGet.php` запрашивал у старого BarcodeGen описание полей выбранного профиля. То есть состав формы зависел не просто от штата, а от полной комбинации `country + state + revision + document type`.

#### Шаг 2. PHP собирал одну большую редактируемую форму

В режиме `Regular` код создавал обычные ручные поля имени, адреса, даты рождения и других данных человека. Затем он находил в metadata выбранного профиля специальные поля:

- `DAQ` — номер документа;
- `DBD` — дата выдачи;
- `DBA` — дата окончания;
- `DCK` — inventory control number;
- `DCF` — document discriminator;
- в некоторых профилях также `DCJ` и другие profile-specific поля.

Эти поля вставлялись в ту же форму в заранее заданном порядке. Они не были read-only: пользователь мог ввести или исправить значение вручную.

#### Шаг 3. Возле поддерживаемого поля появлялась отдельная кнопка `Generate`

Для каждого специального поля PHP смотрел metadata самого поля:

- ключ `randomHandler` означал вызов старого `getRandom`;
- ключ `calculateHandler` означал вызов старого `getCalculate`;
- строка-ключ handler-а содержала список зависимостей через запятую, например `DAJ,DBB`.

По этому списку PHP создавал JavaScript конкретной кнопки. Кнопка не запускала всю цепочку. Она обслуживала только одно поле или одну явно сгруппированную пару полей.

Фактические связи в UI были следующими:

| Поле формы | Что делала кнопка | Куда записывался ответ |
|---|---|---|
| `DAQ` | один вызов random для `DAQ` с текущими зависимостями из metadata | только `DAQ` |
| `DBD` | один grouped random с outputs `DBD, DBA` | сразу `DBD` и `DBA` |
| `DBA` | один calculate только для expiration date | только `DBA` |
| `DCK` | один calculate для inventory number | только `DCK` |
| `DCF` | один calculate для discriminator | только `DCF` |

Таким образом, `DBD + DBA` уже тогда рассматривались как один атомарный результат одного клика, а не как два независимых случайных запроса.

#### Шаг 4. При клике читались текущие значения зависимостей

Обработчик кнопки проходил по списку зависимостей handler-а и в момент клика читал текущие значения полей формы. Это важно: использовался не первоначальный снимок формы, а то, что пользователь успел ввести или исправить к этому моменту.

Legacy JavaScript склеивал данные в строку вида:

```text
DAJ/CA,DBB/07191994
```

Затем он отправлял эту строку и имя ожидаемого результата в один из PHP proxy-файлов. Такой строковый формат был технической особенностью старого frontend и не является новым контрактом.

#### Шаг 5. PHP proxy превращал клик в один вызов BarcodeGen

`getRandom.php`, `getRandom2.php`, `getCalculate.php`, `getCalculate2.php` и `getCalculate3.php` выполняли одинаковую роль:

1. разбирали строку `OBJ` на пары код/значение;
2. удаляли разделители из дат там, где это было нужно;
3. собирали старый BarcodeGen payload вида `values.req + values.res`;
4. вызывали ровно один endpoint `getRandom` или `getCalculate`;
5. переименовывали позиционный ответ в удобное для конкретного JavaScript обработчика поле.

Например:

- `getRandom.php` возвращал одно значение как `desc` для `DAQ`;
- `getRandom2.php` запрашивал `DBD` и `DBA`, затем возвращал их как `ISS` и `EXP`;
- три calculate proxy запрашивали отдельно `DBA`, `DCK` и `DCF`.

Это были не разные бизнес-операции. Это были пять почти одинаковых proxy-файлов из-за ограничений старой реализации.

#### Шаг 6. Ответ применялся как маленький patch формы

После успешного ответа JavaScript менял только output-поля нажатой кнопки:

- клик `DAQ` не менял даты;
- клик `DCK` не менял `DAQ`;
- клик дат менял одновременно `DBD` и `DBA`;
- весь остальной draft оставался без изменений.

После успеха старая кнопка скрывалась, но заполненное поле оставалось обычным редактируемым input. Пользователь мог вручную изменить сгенерированное значение перед финальной отправкой формы.

#### Шаг 7. Regular submit отправлял именно отредактированный полный draft

Для формы `Regular` обработчик в `barcodes/index.php` сериализовал `#GenBar` и отправлял его обратно в `stateGet.php`. Финальная ветка `stateGet.php`, начинающаяся с `if(isset($_POST['DCG']))`, читала из POST в том числе:

- `DAQ`;
- `DBD`;
- `DBA`;
- `DCK`;
- `DCF`;
- остальные ручные поля формы.

Она удаляла визуальные разделители дат, нормализовала регистр, собирала одну итоговую engine-карту и вызывала старый `genBarcode`. Поэтому значение, полученное отдельной кнопкой или исправленное пользователем после кнопки, действительно попадало в final render. На финальном Regular submit эта ветка не вызывала `getRandom`/`getCalculate` повторно.

Именно это поведение является прямым референсом нового Prepared Generate:

```text
текущий полный draft формы → нормализация/валидация → render
```

#### Шаг 8. `genBar.php` был отдельным Auto-подобным путём

Упомянутый `barcodes/genBar.php` реализовывал другой старый путь. Он:

1. читал базовые POST-поля, но инициализировал `DBD`, `DAQ`, `DBA`, `DCK`, `DCF` пустыми;
2. собирал все известные engine values в `$Msearch`;
3. повторно получал metadata профиля;
4. выбирал описания `DBD`, `DAQ`, `DCF`, `DCK`;
5. сортировал их в фиксированный порядок;
6. вызывал random/calculate;
7. после каждого результата пересобирал `$Msearch`, чтобы следующий шаг видел новое значение;
8. отправлял автоматически собранную engine-карту в `genBarcode`.

Этот файл полезен как подтверждение двух внутренних правил: существует одна общая карта значений, и результат предыдущего action становится input следующего. Но сам `genBar.php` является референсом Auto-последовательности, а не целевого Prepared submit.

Иными словами, в legacy существовали оба сценария:

- `Regular` в `stateGet.php`: отдельные кнопки, редактируемый полный draft, затем render текущих значений;
- `genBar.php`: автоматическая derive-последовательность, затем render.

Новый ручной Prepared должен повторить первый сценарий на современных Nuxt + BFF контрактах. Он не должен ссылаться на PHP и не должен переносить автоматический блок `genBar.php` в финальную кнопку.

#### Что именно берём из legacy

```text
metadata exact-профиля
  → известные зависимости одного действия
  → отдельная кнопка возле output-поля
  → один downstream вызов
  → маленький patch текущей формы
  → результат можно отредактировать вручную
```

#### Что именно не переносим

- ссылки и переходы на старый PHP frontend;
- вызовы `stateGet.php`, `getRandom*.php`, `getCalculate*.php` или `genBar.php` из нового приложения;
- AAMVA-коды в состоянии нового frontend;
- чтение зависимостей через DOM selectors;
- строки вида `DAJ/CA,DBB/07191994`;
- ручную склейку JSON;
- отдельный proxy-файл для каждого output;
- позиционный разбор downstream-ответа;
- скрытие кнопки навсегда после первого успеха;
- автоматический повторный random/calculate во время финального Prepared Generate;
- старые URL и загрузку PNG на диск PHP-сервером.

### 2.2. BarcodeGen

Источники состава формы и действий:

- `barcode_gen/src/barcode/barcode.controller.ts` — schema, random, calculate и render routes;
- `barcode_gen/src/barcode/dto/get-data.dto.ts` — контекст формы;
- `barcode_gen/src/barcode-config/helper/present-fields.helper.ts` — полная форма `top + down[type]` при `flag=false`;
- `barcode_gen/src/barcode-config/helper/config.helper.ts:92-198` — в legacy metadata действительно объявлены random handlers для имени, DOB и адреса;
- `barcode_gen/src/barcode-config/barcode-config.service.ts` — поля, options, `randomHandler`, `calculateHandler` и builders ревизий;
- например, CA DL находится в `barcode-config.service.ts:42-192`: `DAQ` random, grouped dates, `DBA`, `DCK` и `DCF` calculate handlers записаны возле самих полей.

BFF получает этот полный metadata payload у BarcodeGen в runtime для выбранного exact-профиля и на его основе строит полную Prepared schema. Наличие handler в metadata не означает автоматическое включение его в публичный action contract: handlers имени, DOB и адреса BFF фильтрует, а для номера документа, дат и служебных идентификаторов применяет policy/exceptions раздела 7.

### 2.3. Что уже есть в BFF

В BFF уже реализованы части Auto Flow, которые нужно переиспользовать, а не дублировать:

- `internal/usecase/chain_executor.go` — последовательное выполнение grouped random/calculate в Auto;
- `generate_usecase.go` — текущая развилка `auto`/`prepared`, Billing и render;
- `internal/domain/engine_fields.go` — semantic → AAMVA mapping;
- `internal/adapters/barcodegen/legacy/client.go` — BarcodeGen adapter;
- `internal/adapters/barcodegen/legacy/internal_client.go` — grouped derive и internal render;
- `configs/revisions/*.yaml` — exact revision identity, server-owned defaults/constants и render policy; после рефакторинга это не копия полной UI-формы BarcodeGen.

Prepared schema/action должны переиспользовать BarcodeGen adapter: schema получает полный payload через `POST /api/v1/barcodes`, action повторно разрешает handler по этому payload и вызывает один low-level random/calculate. `ChainExecutor` не используется, потому что он продолжает Auto-цепочку, а ручной клик обязан выполнить ровно один action.

### 2.4. Что сейчас делает новый Nuxt frontend

Новый frontend из переданного репозитория — приложение на Nuxt 4, Vue 3 и Pinia. Изменения нужно делать в нём. Старый PHP frontend в новый проект не встраивается.

Основные текущие файлы:

- `app/pages/generating.vue` — экран выбора ревизии, построение формы и финальный submit;
- `app/shared/api/services/barcodeGeneration.service.ts` — вызовы revisions/schema/prepare/generate;
- `app/shared/api/types.ts` — `RevisionSchema`, `RevisionFieldSchema`, `PrepareResult`, `GenerateResult`;
- `app/shared/store/useBarcodeGeneration.ts` — сохранение schema и результатов генерации;
- `app/shared/ui/Input.vue`, `Select.vue`, `Button.vue` — существующие UI-примитивы.

Текущий код `generating.vue` выполняет другой сценарий:

1. При mount вызывает `listRevisions()` и автоматически выбирает первую ревизию.
2. Через `getSchema(name)` получает текущую BFF schema.
3. Вычисляет `formFields`, исключая всё, что перечислено в `schema.generatedFields`.
4. Создаёт flat-карту `form[field.name]` только для оставшихся полей.
5. На финальный клик собирает только непустые значения и сам преобразует browser date `YYYY-MM-DD` в engine-подобный `MMDDYYYY`.
6. Сначала вызывает `prepare(revision, cleaned)`, то есть запускает всю derive-цепочку.
7. Затем отправляет `prepare.data.draftFields` в `generate(mode="prepared")`.
8. Если `prepare` упал, код всё равно пытается вызвать `generate(mode="prepared")` с исходными полями.

Это не целевой ручной Prepared flow по следующим причинам:

- generated/calculated поля скрыты, поэтому пользователь не видит и не редактирует полный draft;
- рядом с полями нет отдельных Random/Calculate кнопок;
- один финальный клик сначала автоматически выполняет всю chain;
- frontend зависит от mixed `draftFields`, в котором могут находиться AAMVA-коды;
- frontend сам кодирует semantic dates в engine-формат;
- ошибка `prepare` проглатывается и меняет путь выполнения неявно;
- service и TypeScript types пока не содержат контракта одного action.

Целевая реализация — рефакторинг этого экрана и этого API service:

```text
list revisions
  → GET полной prepared schema выбранной revision
  → показать все semantic fields
  → отдельные необязательные action-кнопки меняют только свои outputs
  → финальная кнопка напрямую вызывает generate(mode="prepared")
```

`prepare()` остаётся в service для отдельного Auto/чернового сценария, если он нужен продукту, но экран ручного Prepared не вызывает его ни при загрузке, ни при action, ни перед финальным generate.

### 2.5. Что подтверждено текущим кодом, а что является целевым изменением

На момент проверки реальный flow такой:

1. `GET /api/v1/revisions/:revision/schema` отдаёт static BFF schema с semantic names; query `mode=prepared` сейчас handler-ом не используется.
2. Текущая `RevisionSchema` содержит `revision`, `displayName`, `baseInput`, `generatedFields`, `fields`, `groups`, но не содержит semantic action metadata.
3. Текущий frontend хранит значения по `field.name`, вызывает `prepare`, получает draft и затем отправляет его в `generate(mode="prepared")`.
4. `prepare` и Auto используют `ChainExecutor`; он добавляет engine keys, поэтому возвращаемый `draftFields` является смешанным public/engine объектом.
5. Текущий frontend работает именно потому, что финально отправляет этот mixed draft, а не чистый semantic Prepared payload.
6. В `generate_usecase.go` `filterRenderValues` сейчас вызывается **до** `NormalizeEngineFields`. Прямой `generate(mode="prepared")` с чистыми semantic keys может потерять значения на engine allowlist.
7. `GenerateRequest.Fields` пока является свободным `map[string]any`; raw engine keys не отклоняются строго на публичной границе.
8. Raw-only payload также не является контрактом: required-поля проверяются по semantic names. Mixed payload — временная утечка реализации, а не формат, который надо сохранить.
9. `POST /api/v1/barcode/action`, Prepared schema с action metadata и per-action validation сейчас отсутствуют.
10. В текущем `generate_usecase.go` условие `mode != "prepared"` должно быть заменено exact switch: неизвестный mode не имеет права запускать Auto.
11. В новом frontend отсутствуют action state, защита от устаревших ответов и стабильный idempotency key для повтора одного и того же финального запроса.

После реализации граница обязана быть следующей:

- browser, Vue state и публичные BFF endpoints используют только canonical semantic keys;
- публичные даты передаются как calendar date `YYYY-MM-DD`;
- AAMVA-коды и country-specific кодирование дат существуют только внутри BFF и BarcodeGen adapter;
- raw AAMVA/server-owned/unknown key во внешнем `fields` или `values` отклоняется до любых внешних вызовов;
- один Prepared action повторно разрешается по exact-profile metadata BarcodeGen и выполняет ровно один random/calculate;
- финальный Prepared Generate выполняет validation, semantic → engine mapping, Billing и render, но не derive chain.

## 3. Два режима, а не три

| Этап | `auto` | `prepared` |
|---|---|---|
| Форма | сокращённая | полная форма ревизии |
| Служебные поля | обычно скрыты | видимы и редактируемы |
| Random/Calculate | BFF запускает сам | только после клика пользователя |
| Следующий action | запускается executor-ом | только разблокируется в UI |
| Ручное изменение результата | через draft | в любой момент в форме |
| Финальная кнопка | выполняет chain и render | только validation, Billing и render |

Отдельный `mode=standard` не нужен. В публичном контракте остаются:

```text
mode=auto
mode=prepared
```

`POST /api/v1/barcode/prepare` может оставаться частью Auto Flow. Новый ручной Prepared flow не обязан вызывать `prepare`: пользователь уже готовит полный draft в форме.

## 4. Получение полной Prepared-формы: frontend → BFF → BarcodeGen → BFF → frontend

После выбора exact revision frontend делает один запрос только в BFF:

```http
GET /api/v1/revisions/US_CA_08292017/schema?mode=prepared
```

`US_CA_08292017` уже однозначно определяет страну, штат, дату ревизии и тип документа. Frontend не должен дополнительно присылать конкурирующие `country`, `state`, `revisionDate` или `documentType` в query. Если selector состоит из нескольких шагов, его итогом всё равно является один exact revision ID из `GET /api/v1/revisions`.

BFF **не собирает полную форму из собственного дубликата BarcodeGen config**. На каждый успешный schema request BFF сам получает полный payload нужного профиля из BarcodeGen существующей ручкой:

```http
POST {BARCODEGEN_URL}/api/v1/barcodes
Authorization: Bearer <service JWT minted by BFF>
Content-Type: application/json
```

Для California DL BFF отправляет:

```json
{
  "country": "USA",
  "DAJ": "CA",
  "DDB": "08292017",
  "type": "DL",
  "flag": false
}
```

Для `US_CA_08292017_ID` отличается только `type: "ID"`. Для Ontario/Alberta BFF отправляет `country: "CAN"` и соответствующие `DAJ/DDB/type`. Для Michigan BFF обязан отправить literal `DDB: "Rev 01-21-2011"`, а не пытаться превратить его в восемь цифр.

`flag` всегда равен `false`: только так BarcodeGen возвращает **полный payload формы** — общую часть `top` и профильную часть `down[type]`, включая controls, values/options, defaults, `randomHandler` и `calculateHandler`. `flag=true` для Prepared запрещён, потому что он возвращает только нижнюю профильную часть и создаёт неполную форму.

Дальше BFF выполняет ровно пять операций:

1. Разбирает exact revision ID в доверенный server-owned context `{country, DAJ, DDB, type}`. Эти значения не берутся из query browser-а.
2. Вызывает `POST /api/v1/barcodes` с `flag=false` и service JWT. Browser никогда не вызывает BarcodeGen напрямую и не получает его token.
3. Проверяет, что ответ действительно относится к запрошенным `DAJ`, `DDB` и `type`. Это обязательно: текущий `PresentFields` BarcodeGen при промахе может молча вернуть первый config. Несовпадение является `502 BARCODEGEN_PROFILE_MISMATCH`, а не поводом показать чужой штат.
4. Нормализует вложенный BarcodeGen payload в публичный `PreparedRevisionSchema`: flatten полей, canonical semantic names, typed options, controls и action metadata. Нормализация не имеет права выбросить поле только потому, что оно generated/calculated или имеет пустой default.
5. Отдаёт полный нормализованный payload frontend-у ответом на исходный `GET .../schema?mode=prepared`.

Итоговая runtime-цепочка загрузки формы:

```text
Nuxt frontend
  → GET BFF /api/v1/revisions/{exactRevision}/schema?mode=prepared
  → BFF resolves {country, DAJ, DDB, type}
  → POST BarcodeGen /api/v1/barcodes {country, DAJ, DDB, type, flag:false}
  → BarcodeGen returns full top + down[type] payload
  → BFF validates exact profile and normalizes all fields/actions
  → BFF returns full PreparedRevisionSchema
  → frontend renders every schema.fields item
```

Здесь нет второго runtime source of truth. BarcodeGen является источником состава формы, controls, options и handlers. BFF хранит только каталог exact revision → BarcodeGen context, semantic/AAMVA mapping и правила безопасности Prepared mode. Полный список UI-полей в BFF повторно не hardcode-ится.

Первую реализацию не нужно усложнять cache/session-механизмом. Один schema request делает один metadata call. Если позже понадобится cache, он может быть коротким server-side cache по ключу `{country, DAJ, DDB, type}`; cache не меняет контракт и не переносится во frontend.

### 4.1. Единственный целевой TypeScript-контракт schema

Существующие frontend types нужно расширить до следующего смысла. Названия свойств ниже нормативные; параллельные aliases добавлять нельзя.

```ts
type PreparedValue = string | number | boolean

type PreparedAction = {
  id: string
  type: "random" | "calculate"
  inputFields: string[]
  outputFields: string[]
}

type PreparedFieldOption = {
  value: PreparedValue
  label: string
}

type PreparedField = {
  name: string
  label: string
  type: "string" | "date" | "enum" | "number" | "boolean"
  control: "input" | "calendar" | "select" | "radio" | "toggle"
  required: boolean
  order: number
  fallbackValue?: PreparedValue
  options?: PreparedFieldOption[]
  validation?: {
    minLength?: number
    maxLength?: number
    pattern?: string
    minDate?: string
    maxDate?: string
  }
  action?: PreparedAction
}

type PreparedRevisionSchema = {
  revision: string
  displayName: string
  mode: "prepared"
  revisionEffectiveDate?: string
  fields: PreparedField[]
  groups: Array<{
    name: string
    label: string
    fields: string[]
  }>
}
```

Обязательные правила schema:

1. `revision` — тот же exact ID, который frontend передал в URL.
2. `fields` содержит **все** редактируемые semantic поля Prepared формы, включая generated/calculated outputs.
3. Полный ожидаемый набор равен `schema.fields.map(field => field.name)`. Отдельный второй список `expectedFields` не нужен.
4. `fields` отсортированы или сортируются frontend по `order`.
5. `type=date` использует публичный формат `YYYY-MM-DD`. `MMDDYYYY`/`YYYYMMDD` являются только внутренними engine-форматами BFF.
6. `type=enum` использует только typed `options[{value,label}]`; frontend показывает `label`, но сохраняет и отправляет `value` без преобразования.
7. `fallbackValue`, включая `""`, `0` и `false`, применяется только если свойство реально присутствует в JSON.
8. `action` присутствует только возле поля, у которого должна быть кнопка.
9. Все имена внутри `inputFields` и `outputFields` обязаны существовать в `fields` этой же schema.
10. Один action может вернуть несколько outputs. Для дат action находится у `issueDate`, но его `outputFields` равен `['issueDate', 'expirationDate']`.
11. Если для `expirationDate` exact-профиль поддерживает отдельный calculate, это отдельный `action` самого поля `expirationDate`.
12. `DAJ`, `DDB`, `QQQ`, другие AAMVA-коды и server-owned constants в `fields` не входят.

Внутри exact-profile config BFF дополнительно хранит данные, которые browser не получает:

- semantic → engine mapping;
- engine input/output каждого action;
- server-owned `DAJ/DDB/QQQ`;
- defaults/constants;
- render allowlist;
- required render fields;
- country-specific правила кодирования дат и единиц измерения.

### 4.2. Сокращённый пример schema для California DL

Пример намеренно показывает не всю форму, а только поля, необходимые для понимания action metadata. Реальный ответ обязан вернуть все поля профиля.

```json
{
  "revision": "US_CA_08292017",
  "displayName": "California Driver License (Aug 2017)",
  "mode": "prepared",
  "revisionEffectiveDate": "2017-08-29",
  "fields": [
    {
      "name": "dateOfBirth",
      "label": "Date of Birth",
      "type": "date",
      "control": "calendar",
      "required": true,
      "order": 5
    },
    {
      "name": "realId",
      "label": "Real ID",
      "type": "enum",
      "control": "select",
      "required": false,
      "order": 10,
      "options": [
        { "value": "", "label": "Not set" },
        { "value": "N", "label": "Non-compliant" },
        { "value": "F", "label": "Fully compliant" }
      ],
      "fallbackValue": ""
    },
    {
      "name": "documentNumber",
      "label": "Customer ID Number",
      "type": "string",
      "control": "input",
      "required": true,
      "order": 20,
      "action": {
        "id": "random:documentNumber",
        "type": "random",
        "inputFields": [],
        "outputFields": ["documentNumber"]
      }
    },
    {
      "name": "issueDate",
      "label": "Issue Date",
      "type": "date",
      "control": "calendar",
      "required": true,
      "order": 21,
      "action": {
        "id": "random:documentDates",
        "type": "random",
        "inputFields": ["dateOfBirth", "realId"],
        "outputFields": ["issueDate", "expirationDate"]
      }
    },
    {
      "name": "expirationDate",
      "label": "Expiration Date",
      "type": "date",
      "control": "calendar",
      "required": true,
      "order": 22,
      "action": {
        "id": "calculate:expirationDate",
        "type": "calculate",
        "inputFields": ["dateOfBirth", "issueDate"],
        "outputFields": ["expirationDate"]
      }
    },
    {
      "name": "inventoryNumber",
      "label": "Inventory Control Number",
      "type": "string",
      "control": "input",
      "required": true,
      "order": 23,
      "action": {
        "id": "calculate:inventoryNumber",
        "type": "calculate",
        "inputFields": ["issueDate", "documentNumber"],
        "outputFields": ["inventoryNumber"]
      }
    }
  ],
  "groups": []
}
```

Frontend не видит engine dependencies и не разбирает исходный `randomHandler`. Например, для California action дат browser отправляет `dateOfBirth` и `realId`; BFF сам добавляет внутренние `DAJ=CA` и `DDB=08292017`, затем маппит semantic values в `DBB/DDA` и outputs `DBD/DBA` обратно в `issueDate/expirationDate`.

### 4.3. Откуда берутся actions и почему здесь не нужен второй registry

Actions также берутся из полного runtime payload BarcodeGen:

- `random=true` + `randomHandler` создают одну кнопку `Random`;
- `calculate=true` + `calculateHandler` создают одну кнопку `Calculate`;
- ключ handler-а является точным набором engine inputs;
- значение handler-а является точным набором engine outputs.

BFF не отдаёт browser-у право выбирать произвольные inputs/outputs. При построении schema он переводит разрешённый handler в короткий public action `{id,type,inputFields,outputFields}`. Базовые handlers имени (`DAC/DAD/DCS`), DOB (`DBB`) и адреса (`DAG/DAI/DAK`) BFF намеренно не публикует в Prepared, даже если они присутствуют в полном BarcodeGen payload.

Отдельный `PreparedActions` registry, копирующий все `randomHandler/calculateHandler` для 55 профилей, **не создаётся**. Это и было бы переусложнением и вторым источником истины. На `POST /api/v1/barcode/action` BFF снова получает exact-profile payload ручкой `POST /api/v1/barcodes`, находит action по `actionId`, повторно применяет Prepared allowlist и только после этого вызывает `random` либо `calculate`. Поэтому клиент не может подменить handler, а BFF не хранит его дубликат.

Каталог BFF хранит только то, чего нельзя безопасно брать из browser-а:

1. exact revision → `{country, DAJ, DDB, type}`;
2. canonical semantic ↔ AAMVA mapping;
3. server-owned constants и финальный render allowlist;
4. Prepared allowlist семейств outputs: `DAQ`, `DBD+DBA`, отдельный `DBA`, `DCK`, `DCF`, `DCJ`;
5. profile exceptions из раздела 7: отсутствующий action, исправление ошибочного self-edge и запрет US actions для ON/AB.

Таким образом, форма и handler dependencies принадлежат BarcodeGen; BFF владеет auth, безопасностью, public semantic contract и orchestration. Никакого длинного Prepared-chain BFF автоматически не выполняет.

## 5. Состояние и рендер полной формы в новом frontend

### 5.1. Что хранить

На странице должен существовать один активный exact revision и одна flat semantic-карта его значений:

```ts
type PreparedValue = string | number | boolean

type ActionRuntimeState = {
  status: "idle" | "loading" | "error"
  requestId?: string
  message?: string
}

type PreparedFormState = {
  revision: string
  schema: PreparedRevisionSchema
  values: Record<string, PreparedValue>
  fieldVersions: Record<string, number>
  actions: Record<string, ActionRuntimeState>
  formEpoch: number
}
```

`fieldVersions` и `formEpoch` — frontend-only техническое состояние для защиты от устаревших ответов. Они не отправляются в BFF.

Пример `values`:

```json
{
  "firstName": "JOHN",
  "middleName": "",
  "lastName": "SMITH",
  "dateOfBirth": "1994-07-19",
  "documentNumber": "",
  "issueDate": "",
  "expirationDate": "",
  "inventoryNumber": "",
  "documentDiscriminator": ""
}
```

Страна, штат, дата ревизии и document type не дублируются в `values`. Они уже зафиксированы exact ID `revision`; BFF восстанавливает server-owned context из registry.

### 5.2. Как инициализировать форму после schema response

Алгоритм должен быть один и детерминированный:

1. Увеличить `formEpoch`.
2. Отменить или логически инвалидировать незавершённые schema/action запросы старой ревизии.
3. Очистить прежние field errors, action errors и результат barcode.
4. Для каждого `schema.fields` создать ключ `values[field.name]`.
5. Если JSON field **содержит** свойство `fallbackValue`, записать его буквально, включая `""`, `0` или `false`.
6. Иначе записать `""` как незаполненное UI-значение.
7. Создать `fieldVersions[field.name] = 0`.
8. Создать `actions[action.id] = {status: 'idle'}` для каждого уникального action.
9. Рендерить поля по `groups`; поля, отсутствующие в группах, рендерить после групп по `order`.

Нельзя использовать проверку `if (field.fallbackValue)`: она потеряет `0`, `false` и пустую строку. Нужна проверка наличия свойства, например `Object.hasOwn(field, 'fallbackValue')`.

### 5.3. Как рендерить поле

- `control=input`, `type=string` → обычный текстовый `Input`.
- `control=input`, `type=number` → numeric input, но пустое состояние остаётся `""`; в число значение превращается только при валидном вводе/submit.
- `control=calendar`, `type=date` → browser date control со значением `YYYY-MM-DD`.
- `control=select` → `Select` из typed `options`; хранится `option.value`, показывается `option.label`.
- `control=radio` и `control=toggle` → компонент обязан сохранять фактическое typed value, а не текст label.
- `required=true` → визуальный required marker и клиентская проверка перед submit.
- `validation` → атрибуты/подсказки frontend и повторная обязательная проверка BFF.
- `action` → кнопка в той же строке/группе, что и поле. Текст кнопки определяется `action.type`: `Random` или `Calculate`.

Generated/calculated output — такой же редактируемый input. Кнопка является удобным способом заполнения, а не единственным источником значения.

В `generating.vue` запрещено сохранять текущий фильтр:

```ts
.filter(field => !generated.has(field.name))
```

Prepared должен рендерить весь `schema.fields`. Если Auto и Prepared живут на одном экране, фильтрация допускается только в явно выбранной ветке Auto.

### 5.4. Когда action-кнопка enabled

Кнопка видима всегда, когда у поля есть `action`. Она disabled, если выполняется хотя бы одно условие:

1. этот action уже находится в `loading`;
2. текущая revision/schema ещё загружается;
3. хотя бы один `action.inputFields` не заполнен;
4. хотя бы один input уже известен frontend как невалидный.

Проверка «заполнено» не должна использовать обычный truthy check:

- string заполнен, если `trim() !== ""`;
- number заполнен, если это конечное число;
- boolean всегда является заданным значением, включая `false`.

Disabled в UI — только удобство. BFF в любом случае повторно проверяет inputs.

### 5.5. Ручное редактирование и зависимости

При любом пользовательском изменении поля frontend:

1. записывает новое semantic value;
2. увеличивает `fieldVersions[fieldName]`;
3. очищает ошибку только этого поля;
4. пересчитывает enabled/disabled зависимых кнопок.

Frontend **не очищает** ранее полученные outputs автоматически. Например, изменение `issueDate` не удаляет `inventoryNumber`. Пользователь либо оставляет полный draft как явный override, либо вручную нажимает нужную кнопку ещё раз.

Если action собирается заменить уже непустое output-поле, frontend сначала показывает подтверждение со списком заменяемых labels. При отмене запрос не отправляется.

### 5.6. Защита от race conditions

В момент клика frontend сохраняет:

- текущий `formEpoch`;
- уникальный `requestId`;
- текущие версии всех `inputFields`;
- текущие версии всех `outputFields`.

Ответ применяется только если:

1. revision и `formEpoch` не изменились;
2. `requestId` всё ещё является последним запросом этого action;
3. ни один input и ни один output не был изменён после клика.

Если любое условие нарушено, ответ молча считается устаревшим и не меняет форму. Для grouped action patch применяется целиком или не применяется вообще; частичное обновление `issueDate` без `expirationDate` запрещено.

## 6. Нажатие одной кнопки Random/Calculate

### 6.1. Что отправляет новый frontend

Frontend берёт action только из загруженной schema. Он не конструирует `actionId`, inputs или outputs по имени поля.

Для запроса frontend создаёт `values`, копируя из формы только имена из `action.inputFields`. Весь draft отправлять не нужно. Outputs, engine dependencies и server-owned context в request не отправляются.

```http
POST /api/v1/barcode/action
Content-Type: application/json
```

```json
{
  "revision": "US_CA_08292017",
  "actionId": "calculate:inventoryNumber",
  "values": {
    "issueDate": "2023-04-16",
    "documentNumber": "A1234567"
  }
}
```

Для action без публичных inputs request содержит `"values": {}`. Frontend не добавляет туда `DAJ`, потому что BFF берёт штат из exact revision config.

Один клик выполняет следующий строгий алгоритм BFF:

1. Проверить JSON shape и обязательные `revision`, `actionId`, `values`; лишние top-level properties отклонить.
2. Загрузить enabled exact revision и server-owned `{country,DAJ,DDB,type}`; неизвестная/disabled revision завершается до BarcodeGen.
3. Вызвать BarcodeGen `POST /api/v1/barcodes` с exact context и `flag=false`.
4. Проверить, что BarcodeGen вернул именно запрошенный профиль, и прогнать payload через общий normalizer.
5. Найти action с точным `actionId` в свежей normalized schema и повторно применить Prepared policy/exceptions раздела 7.
6. Убедиться, что набор ключей `values` является точным допустимым semantic input-набором action: неизвестные, output-only, engine и server-owned keys запрещены.
7. Проверить presence, тип, option membership, pattern и date rules каждого input по свежей schema.
8. Добавить server-owned context (`DAJ`, `DDB`, `QQQ`, constants/defaults).
9. Преобразовать только разрешённые semantic inputs в engine values. Даты из `YYYY-MM-DD` кодируются по стране внутри BFF.
10. Вызвать ровно одну internal BarcodeGen route: random либо calculate. Только grouped action дат может повторить тот же random до 10 раз по разделу 6.4; metadata call при retry не повторяется.
11. Не запускать `ChainExecutor` и не продолжать к следующему action.
12. Проверить downstream status, JSON shape и наличие всех объявленных engine outputs.
13. Отбросить неожиданные outputs, даже если BarcodeGen их вернул.
14. Провалидировать outputs по правилам exact-профиля.
15. Атомарно преобразовать outputs обратно в semantic patch.
16. Вернуть patch. Не вызывать Quote, Billing, render, history или сохранение barcode.

Action endpoint является stateless: BFF не хранит черновик между кликами. Источником текущего draft остаётся Vue state.

### 6.2. Успешный ответ и его применение

```json
{
  "success": true,
  "revision": "US_CA_08292017",
  "actionId": "calculate:inventoryNumber",
  "values": {
    "inventoryNumber": "23106A12345670401"
  }
}
```

Перед применением frontend проверяет:

1. ответ относится к текущей `revision`;
2. `actionId` совпадает с отправленным;
3. ключи `response.values` точно совпадают с `action.outputFields`;
4. race-condition проверки раздела 5.6 пройдены.

После этого frontend атомарно применяет patch и увеличивает version каждого изменённого output. Он не заменяет весь draft объектом ответа BFF.

```ts
for (const output of action.outputFields) {
  form.values[output] = response.values[output]
  form.fieldVersions[output] += 1
}
```

### 6.3. Ошибки action endpoint

Нормативное поведение:

| HTTP | `error.code` | Когда |
|---|---|---|
| `400` | `VALIDATION_ERROR` | malformed JSON, неизвестный semantic key, AAMVA/server-owned key, неверный тип верхнего уровня |
| `404` | `REVISION_NOT_FOUND` | exact revision не существует или недоступна пользователю |
| `422` | `ACTION_NOT_ALLOWED` | action не объявлен в этом exact-профиле |
| `422` | `ACTION_INPUT_MISSING` | отсутствует или пуст обязательный input |
| `422` | `ACTION_INPUT_INVALID` | input нарушает type/options/pattern/date rule |
| `422` | `ACTION_OUTPUT_INVALID` | BarcodeGen вернул полный, но недопустимый результат |
| `502` | `BARCODEGEN_PROFILE_MISMATCH` | metadata endpoint молча вернул другой state/revision/type |
| `502` | `BARCODEGEN_METADATA_MISMATCH` | live handler конфликтует с обязательным profile exception раздела 7 |
| `502` | `BARCODEGEN_BAD_RESPONSE` | metadata/derive вернул ошибку, невалидный JSON или неполные outputs |
| `504` | `BARCODEGEN_TIMEOUT` | истёк timeout metadata либо downstream action |

Пример отсутствующих inputs:

```json
{
  "success": false,
  "error": {
    "code": "ACTION_INPUT_MISSING",
    "message": "Заполните поля, необходимые для расчёта inventory number",
    "fields": [
      { "name": "issueDate", "label": "Issue Date" },
      { "name": "documentNumber", "label": "Customer ID Number" }
    ]
  }
}
```

Frontend показывает ошибку возле action и соответствующих полей. Он не очищает прежние outputs и не запускает fallback action.

Неизвестный `actionId` никогда не интерпретируется как имя engine step. Любой AAMVA key, например `DAQ`, возвращает `400 VALIDATION_ERROR` до adapter-а.

### 6.4. Атомарный action дат

`random:documentDates` — один логический action и один semantic patch:

```json
{
  "success": true,
  "revision": "US_CA_08292017",
  "actionId": "random:documentDates",
  "values": {
    "issueDate": "2023-04-16",
    "expirationDate": "2028-07-19"
  }
}
```

Внутри adapter работает с `DBD + DBA`, но эти ключи не пересекают публичную BFF-границу. BFF применяет правила `ISS_DBD_FLOW.md`:

1. Кодирует inputs в engine-формат страны.
2. Запрашивает `DBD + DBA` одним grouped random.
3. Строго разбирает обе даты как calendar dates.
4. Проверяет `revisionEffectiveDate <= issueDate <= today`.
5. Проверяет `issueDate < expirationDate`.
6. При невалидной паре отбрасывает **обе** даты и повторяет тот же grouped random.
7. Делает максимум 10 попыток в рамках одного пользовательского action.
8. После успеха возвращает обе даты в `YYYY-MM-DD`.
9. После десятой ошибки возвращает error без `values`; прежние значения Vue-формы сохраняются.

Повтор даты — единственное разрешённое внутреннее повторение одного action. BFF не переходит после него к `DCK`, `DCF` или любому другому action.

### 6.5. Два profile-specific примера

**Kentucky document number:** его кнопка disabled, пока не заполнены все публичные inputs exact-action.

```json
{
  "revision": "US_KY_08312018",
  "actionId": "random:documentNumber",
  "values": {
    "dateOfBirth": "1994-07-19",
    "lastName": "SMITH",
    "issueDate": "2023-04-16"
  }
}
```

```json
{
  "success": true,
  "revision": "US_KY_08312018",
  "actionId": "random:documentNumber",
  "values": { "documentNumber": "S12345678" }
}
```

BFF сам добавляет `DAJ=KY` и другие server-owned values из профиля.

**Washington:** это два явных клика пользователя, а не одна автоматически продолжаемая цепочка.

```text
клик 1: random:auditCode
результат: форма получила auditCode
кнопка 2 стала enabled
клик 2: calculate:documentDiscriminator
```

После первого ответа frontend записывает только `auditCode`. Он не вызывает второй action автоматически. Внутренние engine-правила при этом остаются `[DAJ] → [DCJ]`, затем `[DAJ, DAQ, DCJ] → [DCF]`.

## 7. Полные ручные цепочки по всем ревизиям

Этот раздел является исполнимой спецификацией для AI-кодера. Он отвечает на три вопроса для каждого шага: **когда frontend включает кнопку, какую ручку вызывает BFF и какие поля разрешено изменить ответом**.

### 7.0. Единственная схема вызовов

Frontend знает только BFF. BFF сам вызывает BarcodeGen; переносить BarcodeGen URL/token во frontend запрещено.

| Этап | Frontend → BFF | BFF → BarcodeGen | Результат |
|---|---|---|---|
| Загрузка формы | `GET /api/v1/revisions/{revision}/schema?mode=prepared` | `POST /api/v1/barcodes` с `{country,DAJ,DDB,type,flag:false}` | полный `top + down[type]` payload нормализуется и возвращается frontend |
| Random-кнопка | `POST /api/v1/barcode/action` | сначала `POST /api/v1/barcodes` для серверной проверки handler-а, затем `POST /api/internal/v1/barcodes/random` | semantic patch только outputs одного action |
| Calculate-кнопка | `POST /api/v1/barcode/action` | сначала `POST /api/v1/barcodes` для серверной проверки handler-а, затем `POST /api/internal/v1/barcodes/calculate` | semantic patch только outputs одного action |
| Финальная Generate | `POST /api/v1/barcode/generate`, `mode=prepared` | `POST /api/internal/v1/barcodes/pdf417` после BFF validation/Billing Block | готовый barcode URL; random/calculate здесь не вызываются |
| Проверка неоднозначного render | frontend продолжает ждать ответ BFF | `GET /api/internal/v1/barcodes/renders/{renderKey}` только после timeout/unknown outcome | BFF завершает Capture либо Release без второго render |

BarcodeGen service-to-service headers для internal routes:

```http
Authorization: Bearer <BARCODEGEN_SERVICE_TOKEN>
X-Service-Token: <BARCODEGEN_SERVICE_TOKEN>
Content-Type: application/json
```

Публичный `POST /api/v1/barcodes` защищён текущим JWT guard, поэтому BFF вызывает его с короткоживущим service JWT, который уже умеет mint-ить legacy adapter. Не нужно добавлять ещё одну config-ручку только ради Prepared mode.

### 7.0.1. Что BFF делает при каждом action-клике

Для **каждой** строки профиля ниже алгоритм одинаков и не должен быть реализован отдельным handler-ом:

1. Frontend берёт `action` из текущего field schema. Он не конструирует engine inputs/outputs.
2. Кнопка enabled только если заполнены её public `inputFields`. `DAJ`, `DDB`, `QQQ` и profile constants не считаются frontend inputs.
3. Frontend отправляет BFF только `revision`, `actionId` и перечисленные semantic values.
4. BFF разрешает exact revision в `{country,DAJ,DDB,type}`.
5. BFF вызывает BarcodeGen `POST /api/v1/barcodes` с `flag=false`, снова находит соответствующий `randomHandler`/`calculateHandler` и проверяет, что action всё ещё разрешён для этого exact profile. Inputs/outputs из browser request не принимаются.
6. BFF собирает engine `input`: добавляет server-owned context и маппит только разрешённые semantic inputs.
7. BFF делает **один logical downstream action**:
   - `random` → `POST /api/internal/v1/barcodes/random`;
   - `calculate` → `POST /api/internal/v1/barcodes/calculate`.
8. BFF проверяет, что BarcodeGen вернул все и только ожидаемые outputs, переводит их в semantic names/date format и отдаёт patch.
9. Frontend применяет только `response.values`. Следующая кнопка может стать enabled, но не нажимается программно.

Если metadata call на шаге 5 неуспешен, BFF возвращает `502 BARCODEGEN_BAD_RESPONSE` (либо `504 BARCODEGEN_TIMEOUT` при timeout) и **не вызывает** random/calculate. Если live handler отличается от разрешённого профилем исключения раздела 7, BFF возвращает `502 BARCODEGEN_METADATA_MISMATCH`; угадывать dependencies или продолжать со старым клиентским handler нельзя.

### 7.0.2. Точные downstream body

Пример grouped dates:

```http
POST /api/internal/v1/barcodes/random
```

```json
{
  "input": {
    "DAJ": "CA",
    "DDB": "08292017",
    "DBB": "07191994",
    "DDA": "1"
  },
  "outputs": ["DBD", "DBA"]
}
```

```json
{
  "values": {
    "DBD": "04162023",
    "DBA": "08182031"
  }
}
```

BFF преобразует ответ в public patch:

```json
{
  "success": true,
  "revision": "US_CA_08292017",
  "actionId": "random:documentDates",
  "values": {
    "issueDate": "2023-04-16",
    "expirationDate": "2031-08-18"
  }
}
```

Пример calculate inventory:

```http
POST /api/internal/v1/barcodes/calculate
```

```json
{
  "input": {
    "DAJ": "CA",
    "DDB": "08292017",
    "DBD": "04162023",
    "DAQ": "D1234567"
  },
  "outputs": ["DCK"]
}
```

В реальном body должны присутствовать **ровно engine inputs конкретной строки профиля ниже**. Пример не отменяет дополнительные `DBB`, `DBA`, `DDA`, `DCK` или другие зависимости, если они перечислены у exact revision.

### 7.0.3. Обязательный контракт BarcodeGen для BFF

AI-кодер сначала проверяет наличие следующих routes в BarcodeGen. Если internal namespace ещё не реализован в целевой ветке BarcodeGen, его нужно добавить **ровно с этими DTO**, а не переключать BFF на старые PHP proxy и не вызывать public render.

```text
POST /api/v1/barcodes
request:  {country, DAJ, DDB, type, flag:false}
response: полный существующий top + down[type] payload
side effects: нет
```

```text
POST /api/internal/v1/barcodes/random
request:  {input: Record<AAMVA, value>, outputs: string[]}
response: {values: Record<AAMVA, value>}
side effects: нет
```

```text
POST /api/internal/v1/barcodes/calculate
request:  {input: Record<AAMVA, value>, outputs: string[]}
response: {values: Record<AAMVA, value>}
side effects: нет
```

```text
POST /api/internal/v1/barcodes/pdf417
request:  {ownerId: string, renderKey: string, values: Record<AAMVA, value>}
response: {id: string, url: string, type: "PDF417"}
side effects: только idempotent render/persist по renderKey; НЕ Billing и НЕ Kafka user event
```

```text
GET /api/internal/v1/barcodes/renders/{renderKey}
response: {status: "SUCCEEDED"|"FAILED"|"IN_PROGRESS", url?, type?, errorCategory?}
side effects: нет
```

Internal random/calculate не возвращают human labels: ключи `values` должны быть AAMVA-кодами, иначе BFF не сможет строго проверить outputs. Все internal routes защищены service token и недоступны browser-у.

Public `POST /api/v1/barcodes/random|calculate` допустим только как временный legacy adapter mode за feature flag, если это уже требуется текущим deployment. Целевой Prepared flow и acceptance tests используют internal routes. Public `POST /api/v1/barcodes/pdf417` в Prepared запрещён: он выполняет пользовательские Billing/Kafka side effects внутри BarcodeGen, а orchestration уже принадлежит BFF.

### 7.0.4. Как читать каждый профиль ниже

Формат строки:

```text
поле — random/calculate: [engine inputs] → [engine outputs]
```

означает полный сценарий:

- BFF публикует соответствующую semantic кнопку только если такой handler пришёл в полном BarcodeGen payload и не запрещён profile exception;
- frontend ждёт только semantic аналоги клиентских engine inputs;
- BFF сам добавляет server-owned engine inputs;
- BFF вызывает `/api/internal/v1/barcodes/random` либо `/calculate` с указанными `outputs`;
- BFF возвращает только semantic аналоги engine outputs;
- ни одна следующая строка профиля не запускается автоматически.

Engine dependencies в профилях переводятся в public inputs так:

| Engine | Public/BFF source |
|---|---|
| `DAJ` | server-owned state из exact revision; frontend не отправляет |
| `DDB` | server-owned revision value; frontend не отправляет |
| `QQQ` и constants | server-owned profile context; frontend не отправляет |
| `DAC` | `firstName` |
| `DAD` | `middleName` |
| `DCS` | `lastName` |
| `DBB` | `dateOfBirth` |
| `DBC` | `sex` |
| `DAY` | `eyeColor` |
| `DDA` | `realId` |
| `DBD` | `issueDate` |
| `DBA` | `expirationDate` |
| `DAQ` | `documentNumber` |
| `DCK` | `inventoryNumber` |
| `DCF` | `documentDiscriminator` |
| `DCJ` | `auditCode` |

Иными словами, если строка профиля содержит `[DAJ, DBD, DAQ]`, frontend button ждёт `issueDate + documentNumber`, а BFF сам добавляет `DAJ`. Если строка содержит только `[DAJ]`, public `inputFields=[]`, поэтому кнопка доступна сразу после загрузки schema.

Нормативные action IDs и patches:

- `DAQ` → `random:documentNumber` → `{documentNumber}`;
- `DBD + DBA` → `random:documentDates` → атомарный `{issueDate, expirationDate}`;
- отдельный `DBA` → `calculate:expirationDate` → `{expirationDate}`;
- `DCK` → `calculate:inventoryNumber` → `{inventoryNumber}`;
- `DCF` → `calculate:documentDiscriminator` → `{documentDiscriminator}`;
- `DCJ` → `random:auditCode` → `{auditCode}`.

Базовые данные человека заполняются только вручную. Хотя полный `flag=false` payload содержит handlers для `DAC + DAD + DCS`, `DBB` и `DAG + DAI + DAK`, BFF не проецирует их в Prepared schema и отклоняет попытку вызвать их через action endpoint.

Для grouped dates frontend передаёт semantic `dateOfBirth` и `realId`, если они объявлены public dependencies; `DAJ/DDB` BFF восстанавливает из exact revision. Отдельный calculate `DBA` является другой кнопкой и другим HTTP action, даже если grouped random уже заполнил expiration date.

Ниже перечислены exact engine dependencies всех разрешённых profile-specific actions. Это не порядок автозапуска. Это граф доступности ручных кнопок: после появления недостающего input следующая кнопка только разблокируется.

### 1. California DL — `US_CA_08292017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBA] → [DCF]`.

### 2. California ID — `US_CA_08292017_ID`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBA] → [DCF]`.

### 3. Utah 2021 — `US_UT_06012021`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDB] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 4. Utah 2016 — `US_UT_04152016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDB] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 5. Michigan 2011 — `US_MI_Rev_01-21-2011`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ, DBB, DBA] → [DCK]`.
- Отдельного action для `DCF` нет.

### 6. Illinois 2015 — `US_IL_09172015`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 7. Nevada 2021 — `US_NV_05242021`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 8. Nevada 2008 — `US_NV_10102008`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 9. Georgia 2015 — `US_GA_07012015`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB, DCK] → [DCF]`.
- `county` пользователь выбирает вручную; adapter маппит его в `ZGD`.

### 10. Georgia 2019 — `US_GA_01022019`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.
- Source self-edge `DCK → DCK` не используется: BFF profile exception удаляет self-dependency из live handler перед публикацией/исполнением action.

### 11. North Carolina 2014 — `US_NC_10242014`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 12. Massachusetts 2016 — `US_MA_02222016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 13. Minnesota 2017 — `US_MN_10232017`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 14. Connecticut 2017 — `US_CT_02102017`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 15. Missouri 2020 — `US_MO_01272020`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 16. Iowa 2017 — `US_IA_06292017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- Повторяющийся `DBD` в source metadata дедуплицируется.

### 17. Montana 2015 — `US_MT_10072015`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 18. Nebraska 2021 — `US_NE_04052021`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DCK, DBD] → [DCF]`.

### 19. Rhode Island 2016 — `US_RI_01262016`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 20. Idaho 2023 — `US_ID_01312023`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 21. West Virginia 2019 — `US_WV_06042019`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 22. Arizona 2014 — `US_AZ_02142014`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDA] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DCS, DAC, DBB] → [DCF]`.

### 23. Arizona 2023 — `US_AZ_03012023`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDA] → [DBA]`.
- `DCK` — calculate: `[DAJ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DCS, DAC, DBB] → [DCF]`.

### 24. Kansas 2017 — `US_KS_02242017`

- `DAQ` — random: `[DAJ, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 25. Pennsylvania 2016 — `US_PA_06072016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBB] → [DCF]`.

### 26. Pennsylvania 2022 — `US_PA_03252022`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBB] → [DCF]`.

### 27. Arkansas 2018 — `US_AR_03012018`

- `DAQ` — random: `[DAJ, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DCS, DAC, DBB] → [DCF]`.

### 28. Florida 2019 — `US_FL_05012019`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `safeDriver` — ручной toggle; это не random/calculate action.

### 29. Florida 2020 — `US_FL_03012020`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `safeDriver` — ручной toggle; это не random/calculate action.

### 30. Mississippi 2016 — `US_MS_02222016`

- `DAQ` — random: `[DAJ, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 31. North Dakota 2014 — `US_ND_01082014`

- `DAQ` — random: `[DAJ, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 32. Kentucky 2018 — `US_KY_08312018`

- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DAQ` — random: `[DAJ, DBB, DCS, DBD] → [DAQ]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- Кнопка `DAQ` становится доступна после заполнения `DBD`; сама не запускается после dates action.

### 33. Tennessee 2011 — `US_TN_12022011`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 34. Delaware 2017 — `US_DE_08022017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 35. Indiana 2018 — `US_IN_07242018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 36. Washington 2019 — `US_WA_11122019`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCJ` — random: `[DAJ] → [DCJ]`.
- `DCF` — calculate: `[DAJ, DAQ, DCJ] → [DCF]`.
- Action для `DCK` отсутствует.

### 37. South Carolina 2018 — `US_SC_01012018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- Action для `DCK` отсутствует.

### 38. New Jersey 2020 — `US_NJ_01082020`

- `DAQ` — random: `[DAJ, DAC, DAD, DCS, DBB, DBC, DAY] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD] → [DCF]`.

### 39. Alabama 2014 — `US_AL_11012014`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 40. Colorado 2015 — `US_CO_10302015`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD, DDA] → [DBA]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `DCJ` — random: `[DAJ] → [DCJ]`.
- Action для `DCK` отсутствует.

### 41. Maryland 2016 — `US_MD_06202016`

- `DAQ` — random: `[DAJ, DAD, DAC, DCS, DBB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 42. Wisconsin 2015 — `US_WI_09012015`

- `DAQ` — random: `[DAJ, DAD, DAC, DCS, DBB, DBC] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DBD, DBA] → [DCF]`.

### 43. Oregon 2018 — `US_OR_07092018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.

### 44. Louisiana 2015 — `US_LA_02102015`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `DCJ` (`auditCode`) — только ручной input; random action отсутствует.

### 45. Texas 2016 — `US_TX_10102016`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB, DAQ, DBD] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 46. Texas 2020 — `US_TX_02232020`

- `DAQ` — random: `[DAJ, DDB] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB, DBD] → [DCF]`.

### 47. Virginia 2008 — `US_VA_12102008`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 48. Virginia 2023 — `US_VA_04222023`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DDB] → [DCK]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.

### 49. Ohio 2018 — `US_OH_07012018`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ] → [DCF]`.
- `DAX` не является кнопкой: internal derivation считает `Math.round(DAW * 0.45359237)`.

### 50. New Mexico 2016 — `US_NM_06162016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ, DAQ] → [DCK]`.
- Action для `DCF` отсутствует: builder использует собственное фиксированное значение.

### 51. New York 2017 — `US_NY_09282017`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.
- Action для `DCK` отсутствует.

### 52. New York 2022 — `US_NY_03072022`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCF` — calculate: `[DAJ, DDB] → [DCF]`.
- Action для `DCK` отсутствует.

### 53. New Hampshire 2016 — `US_NH_06092016`

- `DAQ` — random: `[DAJ] → [DAQ]`.
- `DBD + DBA` — random: `[DAJ, DBB, DDB, DDA] → [DBD, DBA]`.
- `DBA` — calculate: `[DAJ, DBB, DBD] → [DBA]`.
- `DCK` — calculate: `[DAJ] → [DCK]`.
- `DCF` — calculate: `[DAJ, DCK] → [DCF]`.

### 54. Ontario 2017 — `CA_ON_08292017`

- Подтверждённых province-specific random/calculate actions нет.
- В source descriptor есть общие handlers, но BFF их не публикует, пока не подтверждены канадские форматы и правила.
- `DAQ`, `DBD`, `DBA`, `DCK`, `DCF` пользователь вводит вручную.
- BFF валидирует даты и канадские форматы, но не запускает чужие US actions.
- Рост переводится в сантиметры adapter-ом; отдельной кнопки для этого нет.

### 55. Alberta 2017 — `CA_AB_08292017`

- Подтверждённых province-specific random/calculate actions нет.
- В source descriptor есть общие handlers, но BFF их не публикует, пока не подтверждены канадские форматы и правила.
- `DAQ`, `DBD`, `DBA`, `DCF` пользователь вводит вручную.
- `DCK` профилю не нужен.
- BFF валидирует даты и канадские форматы, но не запускает чужие US actions.
- Рост и вес переводятся в профильные единицы adapter-ом; отдельных кнопок для этого нет.

## 8. Финальный Prepared Generate

### 8.1. Что делает frontend перед запросом

Финальная кнопка не вызывает `prepare()` и не нажимает actions программно. Она выполняет только следующие шаги:

1. Берёт текущую schema и текущие `values`.
2. Строит новый `fields` объект, проходя **только** по `schema.fields`.
3. Для каждого schema field копирует значение, включая `""`, `0` и `false`.
4. Не добавляет техническое состояние Vue/Pinia, labels, action metadata или context selector-а.
5. Не преобразует `YYYY-MM-DD` в AAMVA date. Публичные dates остаются ISO calendar dates.
6. Выполняет клиентскую validation для быстрого feedback.
7. Если frontend validation прошла, создаёт immutable snapshot payload.
8. Создаёт один `X-Idempotency-Key` для этого snapshot и сохраняет его до окончательного ответа.
9. Блокирует повторный submit того же snapshot, пока запрос выполняется.
10. Вызывает сразу `generate(mode="prepared")`.

Если network request оборвался без окончательного HTTP-ответа, повтор **того же snapshot** использует тот же idempotency key. Если пользователь изменил revision, units или любое field value, это новый snapshot и для него создаётся новый key.

Текущий `barcodeGeneration.service.ts` генерирует key внутри каждого вызова `generate()`. Его нужно изменить так, чтобы page/use-case мог передать уже созданный key и переиспользовать его при безопасном retry.

```http
POST /api/v1/barcode/generate
X-Idempotency-Key: <stable-key-for-this-snapshot>
Content-Type: application/json
```

```json
{
  "revision": "US_CA_08292017",
  "barcodeType": "pdf417",
  "mode": "prepared",
  "units": 1,
  "confirmed": true,
  "fields": {
    "firstName": "JOHN",
    "middleName": "",
    "lastName": "SMITH",
    "suffix": "",
    "dateOfBirth": "1994-07-19",
    "street": "123 MAIN ST",
    "city": "LOS ANGELES",
    "zipCode": "90001",
    "sex": 1,
    "realId": "F",
    "documentNumber": "A1234567",
    "issueDate": "2023-04-16",
    "expirationDate": "2028-07-19",
    "inventoryNumber": "23106A12345670401",
    "documentDiscriminator": "04/16/2023ABCDE/1234/28",
    "vehicleClass": "C",
    "restrictions": "NONE",
    "endorsements": "NONE",
    "height": 70,
    "weightPounds": 180,
    "eyeColor": "BRO",
    "hairColor": "BRO",
    "organDonor": false,
    "veteran": false
  }
}
```

### 8.2. Точный порядок обработки в BFF

BFF в ветке `prepared`:

1. Аутентифицирует пользователя и проверяет idempotency key по общему generate-контракту.
2. Проверяет exact `mode` через `switch`; неизвестный mode отклоняется.
3. Загружает enabled exact revision и разрешает `{country,DAJ,DDB,type}`.
4. Сам вызывает BarcodeGen `POST /api/v1/barcodes` с этим context и `flag=false`; получает свежий полный payload, проверяет exact identity и строит ту же normalized schema, которую видел frontend.
5. Проверяет верхнеуровневые `barcodeType`, `units`, `confirmed` и profile limits.
6. Проверяет, что каждый ключ `fields` является canonical semantic field свежей schema.
7. Отклоняет raw AAMVA, server-owned, alias и неизвестные keys до Quote/Billing.
8. Проверяет required presence. Пробельная string считается пустой; `0` и `false` не считаются отсутствующими.
9. Проверяет типы, typed options membership, min/max, pattern и profile-specific правила.
10. Парсит semantic dates как calendar dates, проверяет реальность даты и ограничения возраста/ревизии.
11. Проверяет совместные правила: `revisionEffectiveDate <= issueDate <= today` и `issueDate < expirationDate`.
12. **Не вызывает** `prepare`, `ChainExecutor`, random или calculate.
13. Маппит semantic draft в новую engine-карту.
14. Кодирует даты и единицы измерения по country/profile rules.
15. Подставляет server-owned `DAJ/DDB/QQQ` и immutable defaults/constants.
16. Строит render payload только по engine `renderAllowlist` exact profile.
17. Проверяет `requiredRenderFields` уже на готовой engine-карте.
18. Только после metadata и всех validation вызывает Quote и Billing Block.
19. Вызывает `POST /api/internal/v1/barcodes/pdf417` ровно один раз с `{ownerId,renderKey,values}`.
20. При успехе делает Capture, записывает history и возвращает barcode list.
21. При timeout/unknown outcome сначала проверяет `GET /api/internal/v1/barcodes/renders/{renderKey}` и не запускает второй render вслепую.
22. При подтверждённой ошибке render делает Release и возвращает нормализованную ошибку.

Metadata call на шаге 4 не является random/calculate и ничего не генерирует. Он нужен, потому что BFF больше не хранит вторую полную копию формы и обязан валидировать final payload по текущему exact BarcodeGen contract, а не по данным, присланным browser-ом.

Псевдокод целевого ветвления:

```go
switch req.Mode {
case "auto":
    values = chainExecutor.ExecuteGrouped(ctx, profile, req.Fields)
case "prepared":
    sourcePayload = barcodeGen.GetFullFields(ctx, profile.Context)
    schema = normalizePreparedSchema(profile, sourcePayload)
    semanticDraft = validateCompletePreparedFields(schema, req.Fields)
    values = mapPreparedSemanticToEngine(profile, semanticDraft)
default:
    return ErrUnsupportedMode
}
```

Engine allowlist применяется **после** semantic mapping. Нельзя сначала фильтровать semantic keys engine allowlist-ом. Нельзя оставлять проверку `if mode != "prepared"`, потому что опечатка в mode иначе запустит Auto.

### 8.3. Успешный ответ BFF

Ответ сохраняет уже используемую новым frontend структуру `barcodes[]`:

```json
{
  "success": true,
  "buildId": "build-7c7a",
  "batchId": "batch-42",
  "barcodes": [
    {
      "url": "https://.../barcodes/7c7a.png",
      "format": "pdf417",
      "generationId": "7c7a"
    }
  ],
  "computed": [],
  "skipped": [],
  "billing": {
    "unitPrice": 0.5,
    "totalCost": 0.5,
    "currency": "USD"
  }
}
```

`computed` и `skipped` в Prepared обычно пустые, потому что финальный запрос ничего не вычисляет. Их можно сохранить для совместимости общего `GenerateResult`, но frontend не должен ожидать там action results.

### 8.4. Неполная форма

```json
{
  "success": false,
  "error": {
    "code": "INCOMPLETE_PREPARED_PAYLOAD",
    "message": "Заполните обязательные поля выбранной ревизии",
    "fields": [
      { "name": "inventoryNumber", "label": "Inventory Control Number" },
      { "name": "documentDiscriminator", "label": "Document Discriminator" }
    ]
  }
}
```

HTTP: `422`. До Billing и BarcodeGen render запрос не доходит.

### 8.5. Невалидное значение

```json
{
  "success": false,
  "error": {
    "code": "PREPARED_FIELD_INVALID",
    "message": "Поле vehicleClass содержит недопустимое значение",
    "fields": [
      {
        "name": "vehicleClass",
        "label": "Vehicle Class",
        "value": "X"
      }
    ]
  }
}
```

HTTP: `422`.

### 8.6. Raw AAMVA key во внешнем payload

Если клиент прислал, например, `"DAQ": "A1234567"` вместо `"documentNumber": "A1234567"`, BFF отвечает `400 VALIDATION_ERROR` с `details.unknownFields=["DAQ"]`. Запрос не доходит до Quote, Billing, helper или render.

## 9. Пошаговый план изменения BFF и нового frontend

Рекомендуемый и проверяемый порядок реализации:

```text
verify/add BarcodeGen internal routes from 7.0.3
  → BFF GetFullFields adapter + runtime schema normalizer
  → BFF action endpoint
  → BFF prepared generate fix
  → BFF/BarcodeGen contract tests
  → frontend types/service
  → frontend controls/page
  → frontend tests
  → browser acceptance
```

Не начинать frontend с временных PHP вызовов или hardcoded action matrix. Frontend подключается к semantic BFF schema/action contract.

### 9.1. BFF: runtime schema из BarcodeGen без дублирования формы

1. Не добавлять `mode=standard`. Публичные mode остаются только `auto` и `prepared`; `prepare` является отдельной операцией, а не третьим generate mode.
2. Зафиксировать canonical semantic vocabulary. Для generated fields использовать только:
   - `documentNumber`, не `idNumber` и не `dlNumber`;
   - `expirationDate`, не `expiryDate`;
   - `inventoryNumber`;
   - `documentDiscriminator`;
   - `auditCode`.
   Существующие общие имена `zipCode`, `height`, `weightPounds` сохранить едиными.
3. Добавить в BarcodeGen adapter метод `GetFullFields(ctx, ProfileContext)`, который вызывает существующий `POST /api/v1/barcodes` с `{country,DAJ,DDB,type,flag:false}`. Не создавать новый HTTP endpoint BarcodeGen и не обращаться к BarcodeGen из handler напрямую.
4. `RevisionSchemaUseCase` получает exact profile store и BarcodeGen adapter. Порядок: lookup exact revision → downstream full payload → exact-profile verification → normalization → response.
5. Расширить `internal/domain/revision_schema.go`:
   - добавить `Control` в `FieldSchema`;
   - заменить конкурирующие `Options []string` + `OptionItems []FieldOption` одним `Options []FieldOption`;
   - сделать `FieldOption.Value` typed (`any` в Go DTO), чтобы `0`/`false` не превращались в строки;
   - добавить optional public `Action`;
   - добавить root `Mode`; ответ на `?mode=prepared` всегда содержит `"mode":"prepared"`.
6. Написать один pure normalizer BarcodeGen payload → `PreparedRevisionSchema`. Он flatten-ит все ряды `top + down[type]`, не фильтрует generated/calculated fields, переводит `abr` в canonical semantic name, сохраняет control/options/default/validation и проецирует разрешённые handlers в public actions.
7. Не создавать `PreparedActions` рядом с live handlers. На schema load и action call action разрешается по exact BarcodeGen payload плюс коротким BFF policy exceptions раздела 7.
8. Для public action использовать только `{id,type,inputFields,outputFields}`. Engine arrays остаются внутри BFF. `DisplayField` вычисляется детерминированно: `DBD+DBA` у `issueDate`, отдельный `DBA` у `expirationDate`, остальные у собственного output field.
9. Не публиковать Prepared actions для имени, DOB и адреса, даже если BarcodeGen payload содержит такие handlers.
10. Проверять результат normalization: уникальные semantic field names/action IDs, известный mapping каждого `abr`, typed options, непустые outputs, существующие input/output fields и отсутствие engine keys в public schema.
11. Проверять exact-profile identity ответа. Молчаливый fallback BarcodeGen на первый config должен превращаться в `502 BARCODEGEN_PROFILE_MISMATCH`.
12. Не добавлять distributed cache, schema session ID или новый persistence layer. Сначала реализовать прямой runtime call; оптимизация cache допустима отдельно и не меняет контракт.

### 9.2. BFF: action endpoint

1. Добавить request/response DTO с точными полями `revision`, `actionId`, `values`.
2. Добавить route `POST /api/v1/barcode/action` в существующий `/api/v1` router и применить ту же auth middleware, что у generation endpoints.
3. Добавить отдельный `PreparedActionUseCase`; HTTP handler только decode → execute → encode.
4. Use case получает exact profile store и один BarcodeGen adapter с методами `GetFullFields`, `Derive(random|calculate)`.
5. На каждый action выполнить явную последовательность: exact profile lookup → `GetFullFields(flag=false)` → найти handler по server-side metadata → применить Prepared policy/exceptions → проверить только объявленные semantic inputs → добавить server context → один `Derive` → проверить outputs → вернуть semantic patch.
6. Не принимать от frontend `kind`, engine `input`, engine `outputs`, `randomHandler` или `calculateHandler`. Даже если клиент добавил их в JSON, strict decoder обязан вернуть `400 VALIDATION_ERROR`.
7. Не инжектировать и не вызывать Billing, render client, history repository или `ChainExecutor` из этого use case.
8. Для date action переиспользовать единый helper атомарной проверки `DBD/DBA` и лимит 10 derive-попыток. Metadata payload запрашивается один раз на action request, а не на каждую retry-попытку.
9. Нормализовать downstream errors в коды таблицы 6.3; не отдавать browser-у сырой body BarcodeGen.
10. Логировать revision/action ID/metadata latency/derive latency/attempt count, но не полный PII draft.
11. Добавить route/use-case tests до подключения frontend.

### 9.3. BFF: финальный generate(mode=prepared)

1. В `generate_usecase.go` заменить условие вида `mode != "prepared"` на exact `switch`.
2. В prepared branch до validation вызвать `GetFullFields(ctx, profile.Context)`; это один `POST /api/v1/barcodes` с `flag=false`, без random/calculate side effects.
3. Нормализовать payload тем же pure normalizer, что использует schema endpoint. Не писать вторую validation schema для generate.
4. Разделить prepared validation и engine mapping на явные функции; не пропускать публичный map напрямую в render filter.
5. Разрешить только field names из свежей exact schema. Это автоматически отклоняет AAMVA, server-owned и старые aliases.
6. Проверить full semantic draft по разделу 8.2.
7. Затем создать новую engine-карту через canonical semantic mapping.
8. Затем применить defaults/constants и country-specific преобразования.
9. Только потом применить `renderAllowlist` и проверить `requiredRenderFields`.
10. Убедиться тестом, что prepared branch не вызывает `ChainExecutor`, random, calculate или `prepare`.
11. Все metadata/validation errors вернуть до Quote/Billing.
12. Render вызвать только через `POST /api/internal/v1/barcodes/pdf417`; public `/api/v1/barcodes/pdf417` не использовать, чтобы BFF и BarcodeGen не сделали Billing/Kafka side effects дважды.
13. Сохранить существующий общий ответ `GenerateResult` с `barcodes[]`, чтобы не ломать отображение результата в новом frontend.

### 9.4. Frontend: `app/shared/api/types.ts`

1. Добавить `PreparedAction`, typed `PreparedFieldOption` и `ActionResult`.
2. Расширить `RevisionFieldSchema` свойствами `control` и `action`.
3. Зафиксировать public date как `YYYY-MM-DD` в типах/тестовых fixtures.
4. Удалить зависимость Prepared UI от `generatedFields`; это Auto metadata, а не список скрытых полей Prepared.
5. Не добавлять AAMVA-коды в union/types frontend.

Нужные DTO:

```ts
interface PreparedActionRequest {
  revision: string
  actionId: string
  values: Record<string, PreparedValue>
}

interface PreparedActionResult {
  success: true
  revision: string
  actionId: string
  values: Record<string, PreparedValue>
}
```

### 9.5. Frontend: `barcodeGeneration.service.ts`

1. Сохранить существующие `listRevisions()`, `getSchema()` и `generate()`.
2. Изменить `getSchema(revision, mode)`, чтобы Prepared page явно вызывала `/revisions/{revision}/schema?mode=prepared`.
3. Добавить `runAction(payload)` → `POST /barcode/action`.
4. В mock mode возвращать patch только для outputs mock-action, а не весь draft.
5. Изменить `generate` так, чтобы caller мог передать `idempotencyKey`; service не должен всегда создавать новый key при retry.
6. Не вызывать `prepare()` внутри `generate()` или `runAction()`.
7. Не добавлять legacy PHP URLs ни в runtime config, ни в service.

### 9.6. Frontend: `app/shared/ui/Select.vue` и остальные controls

Текущий `Select.vue` принимает только `string[]`, показывает строку как label/value и использует `modelValue || ''`. Это не подходит для typed options: `0` и `false` будут потеряны, а label нельзя отделить от value.

Нужно сделать одно из двух: расширить существующий `Select.vue` или создать отдельный typed select. Целевое поведение в обоих случаях одинаковое:

1. Props принимают `Array<{value: string | number | boolean; label: string}>`.
2. UI показывает `label`.
3. `update:modelValue` возвращает исходный typed `value`, а не всегда string из DOM.
4. Empty-state определяется через `modelValue === null || modelValue === undefined`, а не truthy check.
5. Для native `<select>` нельзя полагаться на строковый DOM value для восстановления типа; component должен найти исходный option по стабильному внутреннему key/index и emit его `option.value`.
6. Revision selector тоже переводится на typed options: `value = revision.name`, `label = revision.displayName || revision.name`.
7. Страница хранит выбранный revision ID напрямую. Текущая схема `selectedLabel → find first matching label` удаляется, потому что display labels не обязаны быть уникальными.
8. `Input.vue` может продолжать emit string; `usePreparedForm` явно преобразует валидный number при обновлении/submit. Date input хранит строку `YYYY-MM-DD`.
9. `Button.vue` используется с отдельным `loading` каждого action; loading финального generate не подменяет action loading.

### 9.7. Frontend: `app/pages/generating.vue`

Текущий файл нужно изменить в следующем порядке:

1. Оставить загрузку revision list и schema.
2. Заменить `selectedLabel`/поиск по display label на `selectedRevision`, содержащий exact `revision.name`.
3. Для selector-а строить typed options `{value: revision.name, label: revision.displayName || revision.name}`.
4. При запросе schema передавать `mode=prepared`.
5. Удалить фильтрацию `generatedFields`; `formFields` должен содержать все `schema.fields`, отсортированные по `order`.
6. Инициализировать `values` по алгоритму 5.2 с сохранением falsy fallback values.
7. Удалить `toEngineDate()`: browser date уже имеет нужный публичный ISO-формат.
8. Заменить текущий `buildFields()`, который пропускает `""`, на сбор полного объекта по `schema.fields`.
9. Рендерить control и typed options из schema, а не угадывать UI только по `field.type`.
10. Возле поля с `field.action` рендерить `Random`/`Calculate` button.
11. Добавить per-action loading/error state; общий `loading` оставить только для schema/final generate.
12. Реализовать dependency disabling, overwrite confirmation и race-condition правила раздела 5.
13. В action click вызвать только `service.runAction()` и применить только объявленный patch.
14. Не запускать следующий action автоматически.
15. Полностью удалить из `onGenerate()` вызов `service.prepare()` и его silent `catch` fallback.
16. Финальный `onGenerate()` вызывает только `service.generate({mode: 'prepared', fields: fullSemanticFields})`.
17. При field-level BFF errors связать `error.fields[].name` с соответствующим control; общий error показать над финальной кнопкой.
18. После успеха сохранить `res.data.barcodes` через существующий `store.addBarcodes()`.

Чтобы `generating.vue` не превратился в монолит, допустимо и предпочтительно вынести:

- `PreparedFieldRow.vue` — control + label + field error + action button;
- `usePreparedForm.ts` — initialization, field versions, action state, race protection, full snapshot;
- `PreparedOverwriteDialog.vue` — подтверждение перезаписи нескольких outputs.

Это компоненты нового Nuxt frontend. Они не оборачивают и не открывают старый PHP frontend.

### 9.8. Frontend: store, i18n и error UX

1. `useBarcodeGenerationStore` можно оставить для schema/result history; активную незавершённую форму допустимо держать локально в page/composable.
2. Не сохранять PII draft в localStorage/persisted Pinia.
3. При смене revision очищать barcode result и инвалидировать старые action responses.
4. Добавить i18n keys для `Random`, `Calculate`, missing dependencies, overwrite confirmation, stale/failed action и prepared validation errors.
5. Action error не должен заменять общий final-generate error и наоборот.
6. Loading одного action не должен блокировать ручное редактирование независимых полей; final submit блокируется, пока выполняется любой action, чтобы snapshot не был неоднозначным.

## 10. Обязательные проверки

### 10.1. Runtime metadata/schema contract tests BFF

Для каждого из 55 профилей BarcodeGen payload фиксируется как test fixture ответа `POST /api/v1/barcodes`; отдельную копию полной BFF-формы для теста не создаём. Тест автоматически проверяет:

- exact revision загружается и преобразуется в правильные `{country,DAJ,DDB,type,flag:false}`;
- adapter действительно вызывает `/api/v1/barcodes`, а не PHP URL и не локальный hardcoded form;
- `flag=false` возвращает и `top`, и `down[type]`, и normalizer не теряет ни одного renderable field;
- profile mismatch/fallback BarcodeGen обнаруживается и возвращает `502`;
- публичная schema содержит только canonical semantic field names;
- каждый action ID уникален внутри профиля;
- public semantic inputs/outputs существуют в `schema.fields`;
- handler engine inputs/outputs соответствуют exact payload и исключениям матрицы раздела 7;
- public action projection не содержит engine keys;
- generated/calculated output присутствует как редактируемое поле;
- actions имени, DOB и адреса отфильтрованы;
- enum options typed, а fallback входит в options;
- `revisionEffectiveDate` валидна;
- ON/AB не получают US actions;
- один schema request вызывает BarcodeGen metadata ровно один раз.

### 10.2. Action use-case и HTTP contract tests BFF

Минимальный набор:

1. Разрешённый action сначала вызывает BarcodeGen metadata, затем получает только свои semantic inputs и возвращает только semantic outputs.
2. Metadata request содержит exact `{country,DAJ,DDB,type,flag:false}` и выполняется ровно один раз на action request.
3. Action без публичных inputs работает с `values={}` и получает server context из exact revision.
4. Неизвестный/запрещённый live handler возвращает `422 ACTION_NOT_ALLOWED`; random/calculate downstream не вызывается.
5. Metadata timeout/malformed response/profile mismatch возвращает нормализованный `502`; derive не вызывается.
6. Missing input возвращает `422 ACTION_INPUT_MISSING`.
7. Invalid option/date/pattern возвращает `422 ACTION_INPUT_INVALID`.
8. Raw AAMVA, server-owned, unknown и output-only key возвращает `400 VALIDATION_ERROR`.
9. Клиентские `kind/input/outputs/*Handler` отклоняются strict decoder-ом.
10. Лишний semantic input не отправляется downstream и не игнорируется молча.
11. Неожиданный engine output не попадает в response.
12. Неполный downstream output превращается в `502 BARCODEGEN_BAD_RESPONSE`.
13. Action не вызывает Billing, render, history или следующий action.
14. `DBD/DBA` retry ограничен 10 derive-попытками, использует один metadata snapshot и всегда атомарен.
15. Public date input/output имеет `YYYY-MM-DD`; adapter получает country-specific engine format.
16. Логи не содержат полный PII request body.

### 10.3. Финальный Prepared Generate tests BFF

Нужны отдельные spy/mock assertions:

- prepared request сначала вызывает metadata `POST /api/v1/barcodes` ровно один раз с exact context и `flag=false`;
- metadata error/profile mismatch останавливает flow до Quote/Billing/render;
- clean full semantic payload успешно доходит до internal render;
- semantic mapping происходит до engine allowlist;
- prepared branch ни разу не вызывает `prepare`, `ChainExecutor`, random или calculate;
- unknown mode не попадает в Auto;
- raw AAMVA/server-owned/alias/unknown key отклоняется до Quote/Billing;
- incomplete/invalid prepared payload отклоняется до Quote/Billing;
- `""`, `0` и `false` сохраняются и валидируются правильно;
- ISO dates кодируются в `MMDDYYYY` для US и `YYYYMMDD` для ON/AB только внутри BFF;
- immutable profile constants нельзя подменить;
- render получает только allowlisted engine keys через `/api/internal/v1/barcodes/pdf417`;
- public `/api/v1/barcodes/pdf417` не вызывается;
- Billing Block происходит после metadata/validation, Capture после render success, Release после confirmed render failure;
- render timeout сначала проверяется через `GET /api/internal/v1/barcodes/renders/{renderKey}`;
- одинаковый idempotency key и одинаковый snapshot не создают двойной charge/render/history record;
- успешный ответ соответствует существующему `GenerateResult` с `barcodes[]`.

### 10.4. Frontend unit/component tests

Покрыть новый `usePreparedForm`/страницу:

1. Все schema fields рендерятся; generated outputs не фильтруются.
2. `fallbackValue` сохраняет `""`, `0` и `false`.
3. Enum показывает label и отправляет typed value.
4. Date остаётся `YYYY-MM-DD`; `toEngineDate` не вызывается и отсутствует.
5. Action button visible только при наличии `field.action`.
6. Missing dependency делает action disabled; `false` считается заполненным boolean.
7. Один action показывает loading только для себя.
8. Успех меняет только `outputFields`.
9. Error не очищает существующее значение.
10. Grouped dates применяются обе или не применяются ни одна.
11. Изменение input/output во время запроса делает ответ stale.
12. Смена revision делает старый ответ stale.
13. Следующий action после patch только разблокируется, но не вызывается.
14. Непустой output требует overwrite confirmation.
15. Final snapshot содержит все schema fields, включая falsy values.
16. Final click не вызывает `prepare()`.
17. Retry неизменённого snapshot использует прежний idempotency key; изменённый snapshot получает новый.
18. В HTTP payload отсутствуют AAMVA-коды и frontend runtime state.

### 10.5. Browser acceptance flow

На реальном Nuxt экране проверить минимум California и Washington:

**California:**

1. Выбрать revision.
2. Убедиться, что видны ручные и generated fields.
3. Заполнить DOB/Real ID.
4. Нажать Random dates и увидеть две даты.
5. Вручную исправить expiration date.
6. Нажать Calculate inventory; остальные поля не должны измениться.
7. Нажать финальную Generate.
8. В Network убедиться: перед `/barcode/generate` не было `/barcode/prepare`; final body содержит semantic ISO dates.
9. Убедиться, что появился `barcodes[0].url`.

**Washington:**

1. Нажать Random audit code.
2. Убедиться, что discriminator request не отправился автоматически.
3. Убедиться, что его кнопка стала enabled.
4. Нажать её отдельно и получить только discriminator patch.

### 10.6. Особые profile fixtures

Обязательны отдельные тесты:

- California DL и ID как разные exact profiles;
- Michigan literal revision `Rev 01-21-2011`;
- Kentucky: `DAQ` зависит от `DBD`, но не запускается после dates action;
- Georgia 2019: self-edge `DCK → DCK` отсутствует;
- Iowa: повторный `DBD` дедуплицирован;
- WA/CO: отдельный random `DCJ`;
- LA: `DCJ` только ручной;
- Ohio: `DAX` выводится из `DAW` без UI-action;
- New Mexico: нет action `DCF`;
- NY/WA/SC/CO/AB: нет action `DCK`;
- ON/AB: нет подстановки US actions;
- даты возвращаются и применяются атомарно.

## 11. Критерии приёмки

Реализация считается завершённой только если одновременно выполняются все пункты:

1. Изменён новый Nuxt frontend; пользователь не перенаправляется на старый PHP frontend.
2. В runtime нового frontend нет URL или вызовов `stateGet.php`, `getRandom*.php`, `getCalculate*.php`, `genBar.php`.
3. В системе остаются только generate modes `auto` и `prepared`.
4. Prepared schema показывает полную semantic-форму exact revision, включая generated/calculated outputs.
5. Frontend хранит и отправляет только canonical semantic keys и ISO calendar dates.
6. AAMVA-коды и engine date formats существуют только внутри BFF/BarcodeGen boundary.
7. Raw AAMVA, server-owned, old alias или unknown key отклоняется до Quote/Billing.
8. Пользователь может полностью заполнить Prepared форму без использования actions.
9. Имя, DOB и адрес не имеют Prepared actions и никогда не генерируются этими кнопками.
10. Каждая показанная кнопка получена BFF из полного exact-profile payload BarcodeGen и пропущена через Prepared policy.
11. Один клик выполняет один metadata lookup и один logical random/calculate action; date retry не запускает другие actions.
12. Следующий action никогда не запускается автоматически.
13. BFF не доверяет присланным клиентом dependencies/outputs/handlers и повторно разрешает action по server-side metadata.
14. Action request содержит `revision`, `actionId` и только разрешённые semantic inputs.
15. Action response содержит только semantic patch объявленных outputs.
16. Ошибка или stale response не очищает и не перезаписывает форму.
17. `issueDate + expirationDate` генерируются, валидируются и применяются атомарно.
18. После action любое output-поле остаётся редактируемым.
19. Ручное изменение dependency не вызывает скрытый пересчёт и не очищает outputs.
20. Финальный клик Prepared напрямую вызывает `generate(mode=prepared)` без `prepare`.
21. Финальный `mode=prepared` не вызывает `ChainExecutor`, random или calculate.
22. Полнота и validation проверяются до Quote/Billing.
23. Semantic → engine mapping выполняется до render allowlist.
24. Неизвестный mode не попадает в Auto Flow.
25. Повтор одного неизменённого final snapshot не создаёт двойной charge/render благодаря стабильному idempotency key.
26. Успешный frontend использует существующий ответ `GenerateResult.barcodes[]`.
27. Все 55 профилей покрыты runtime-metadata fixtures и contract tests.
28. ON/AB не получают выдуманные US helper rules.
29. Vue state не сохраняет PII draft в localStorage/persisted store.
30. California и Washington browser acceptance flows из раздела 10.5 проходят.
31. При каждом выборе revision BFF сам вызывает BarcodeGen `POST /api/v1/barcodes` с exact context и `flag=false`, получает полный payload и отдаёт frontend полную нормализованную schema.
32. Browser ни на одном этапе не вызывает BarcodeGen напрямую и не получает BarcodeGen credentials.
33. Schema/action не используют второй hardcoded список полной формы или копию всех handlers в BFF.
34. BFF выявляет silent fallback BarcodeGen на чужой state/revision и не показывает такой payload пользователю.
35. Random вызывает только `/api/internal/v1/barcodes/random`, calculate — только `/api/internal/v1/barcodes/calculate`, финальный PDF417 render — только `/api/internal/v1/barcodes/pdf417`.
