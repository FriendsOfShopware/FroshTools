import assert from "node:assert/strict";
import { randomUUID } from "node:crypto";
import { mkdir, readFile, rm } from "node:fs/promises";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
import path from "node:path";
import puppeteer from "puppeteer-core";

const required = (name) => {
  assert.ok(process.env[name], `Set ${name} for this disposable fixture test`);
  return process.env[name];
};
const baseUrl = required("FROSH_BROWSER_BASE_URL").replace(/\/?$/, "/");
const adminUrl = new URL("admin", baseUrl).href;
const artifacts = required("FROSH_BROWSER_ARTIFACTS");
await mkdir(artifacts, { recursive: true });
await rm(path.join(artifacts, "security-activity.json"), { force: true });
const username = required("FROSH_BROWSER_USERNAME");
const password = required("FROSH_BROWSER_PASSWORD");
const login = await fetch(new URL("api/oauth/token", baseUrl), {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({
    grant_type: "password",
    client_id: "administration",
    scope: "write user-verified",
    username,
    password,
  }),
});
assert.equal(login.status, 200);
const auth = await login.json();
const api = (route, method = "GET", body) =>
  fetch(new URL(`api/${route}`, baseUrl), {
    method,
    headers: {
      Authorization: `Bearer ${auth.access_token}`,
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    ...(body ? { body: JSON.stringify(body) } : {}),
  });
const users = await api("search/user", "POST", {
  filter: [{ type: "equals", field: "username", value: username }],
  limit: 1,
});
assert.equal(users.status, 200);
const operator = (await users.json()).data[0];
const id = randomUUID().replaceAll("-", "");
const actor = `frosh-timeline-${id}`;
const fixture = required("FROSH_BROWSER_TIMELINE_FIXTURE");
const browser = await puppeteer.launch({
  executablePath: required("FROSH_BROWSER_CHROME"),
  headless: true,
  args: ["--no-sandbox"],
});
const page = await browser.newPage();
await page.setViewport({ width: 1600, height: 1200 });
const cdp = await browser.target().createCDPSession();
await cdp.send("Browser.setDownloadBehavior", {
  behavior: "allow",
  downloadPath: artifacts,
  eventsEnabled: true,
});
const errors = [];
page.on("pageerror", (error) => errors.push(error.message));
let created = false;
let stage = "setup";
const select = async (index, value, search = false) => {
  stage = `select ${index}: ${value}`;
  const fields = await page.$$(".frosh-security-timeline__select");
  await (await fields[index].$(".sw-single-select__selection")).click();
  if (search) await (await fields[index].$("input")).type(value);
  await page.locator(`.sw-select-result ::-p-text(${value})`).click();
};
const listResponse = () => {
  const pending = page.waitForResponse(
    (response) =>
      new URL(response.url()).pathname.endsWith("/security/activity") &&
      response.request().method() === "GET",
  );
  pending.catch(() => {});
  return pending;
};
const apply = async () => {
  const result = listResponse();
  await page.click('.frosh-security-timeline__filters button[type="submit"]');
  const response = await result;
  assert.equal(response.status(), 200);
  const body = await response.json();
  await page.waitForFunction(
    (count) =>
      document.querySelectorAll(".frosh-security-timeline tbody tr").length ===
      Math.min(count, 25),
    {},
    body.total,
  );
  return body;
};
const setInput = (name, value) =>
  page.$eval(
    `.frosh-security-timeline input[name="${name}"]`,
    (input, value) => {
      input.value = value;
      input.dispatchEvent(new Event("input", { bubbles: true }));
    },
    value,
  );
try {
  const response = await api("user", "POST", {
    id,
    username: actor,
    password: `Fixture-${randomUUID()}`,
    firstName: "Timeline",
    lastName: "Fixture",
    email: `${id}@example.com`,
    localeId: operator.localeId,
    active: true,
    admin: false,
  });
  assert.equal(response.status, 204, await response.text());
  created = true;
  await promisify(execFile)(fixture, [id, "seed"], { timeout: 120000 });
  await page.goto(adminUrl, { waitUntil: "networkidle0", timeout: 120000 });
  await page.type("input[type=text]", username);
  await page.type("input[type=password]", password);
  await page.locator("button ::-p-text(Log in)").click();
  await page.waitForSelector(".sw-admin-menu", { timeout: 60000 });
  await page.goto(
    `${adminUrl}#/frosh/tools/index/security?section=timeline&subjectType=users&subjectId=${id}`,
    { waitUntil: "networkidle0", timeout: 120000 },
  );
  await page.waitForSelector(".frosh-security-timeline tbody tr");
  assert.ok(
    (
      await page.$eval(
        ".frosh-security-timeline__subject",
        (el) => el.textContent,
      )
    ).includes(id),
  );
  await select(0, actor, true);
  await select(1, "Signed in", true);
  const todayRequest = listResponse();
  await select(2, "Today");
  assert.equal((await (await todayRequest).json()).total, 31);
  await setInput("clientIp", "2001:0db8:0:0:0:0:0:27");
  const filtered = await apply();
  const selectionHeights = await page.$$eval(
    ".frosh-security-timeline__select .sw-single-select__selection",
    (nodes) => nodes.map((node) => node.getBoundingClientRect().height),
  );
  assert.ok(
    Math.abs(selectionHeights[0] - selectionHeights[1]) <= 1,
    "Long actor labels keep the filter controls aligned",
  );
  assert.equal(filtered.total, 30);
  assert.ok(
    filtered.entries.every(
      (entry) =>
        entry.action === "user:login" &&
        entry.context.username === actor &&
        entry.context.clientIp === "2001:db8::27",
    ),
  );
  assert.ok(!JSON.stringify(filtered).includes("timeline-private-credential"));
  stage = "pagination and details";
  const secondPage = listResponse();
  await page.click(".frosh-security-timeline .sw-pagination__page-button-next");
  const second = await (await secondPage).json();
  assert.equal(second.entries.length, 5);
  assert.ok(
    second.entries.every(
      (entry) => !filtered.entries.some((previous) => previous.id === entry.id),
    ),
  );
  await page.waitForFunction(
    () =>
      document.querySelectorAll(".frosh-security-timeline tbody tr").length ===
      5,
  );
  for (const close of await page.$$(".sw-notifications .sw-alert__close"))
    await close.click();
  await page.waitForSelector(".sw-notifications", {
    hidden: true,
    timeout: 20000,
  });
  await page.screenshot({
    path: path.join(artifacts, "timeline-filtered.png"),
    fullPage: true,
  });
  await page.click(".frosh-security-timeline tbody .ft-link");
  await page.waitForSelector(".frosh-security-timeline__details");
  const details = JSON.parse(
    await page.$eval(
      ".frosh-security-timeline__details",
      (el) => el.textContent,
    ),
  );
  assert.equal(details.username, actor);
  assert.equal(details.password, undefined);
  assert.equal(details.accessToken, undefined);
  await page.keyboard.press("Escape");
  await page.waitForSelector(".ft-modal", { hidden: true });
  stage = "download filtered export";
  const exported = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith("/security/activity/export"),
  );
  const downloaded = new Promise((resolve, reject) => {
    const timer = setTimeout(
      () => reject(new Error("Timeline download did not finish")),
      30000,
    );
    cdp.on("Browser.downloadProgress", (event) => {
      if (event.state === "completed") {
        clearTimeout(timer);
        resolve();
      }
    });
  });
  await page
    .locator(
      ".frosh-security-timeline button ::-p-text(Export filtered activity)",
    )
    .click();
  const exportResponse = await exported;
  assert.equal(exportResponse.status(), 200);
  const exportBody = await exportResponse.json();
  await downloaded;
  const file = JSON.parse(
    await readFile(path.join(artifacts, "security-activity.json"), "utf8"),
  );
  assert.deepEqual(file, exportBody);
  assert.deepEqual(file.entries, [...filtered.entries, ...second.entries]);
  const exportUrl = new URL(exportResponse.url());
  for (const [key, value] of Object.entries({
    actor,
    action: "user:login",
    exact: "true",
    subjectType: "users",
    subjectId: id,
  }))
    assert.equal(exportUrl.searchParams.get(key), value);
  stage = "custom dates, empty state and clear";
  const yesterday = new Date();
  yesterday.setUTCDate(yesterday.getUTCDate() - 1);
  const day = yesterday.toISOString().slice(0, 10);
  await setInput("from", day);
  await setInput("to", day);
  assert.equal((await apply()).total, 1);
  const periodField = (await page.$$(".frosh-security-timeline__select"))[2];
  const clearPeriod = await periodField.$("[data-clearable-button]");
  if (clearPeriod) {
    const resetRange = listResponse();
    await clearPeriod.click();
    assert.equal((await (await resetRange).json()).total, 31);
    assert.equal(
      await page.$eval(
        '.frosh-security-timeline input[name="from"]',
        (input) => input.value,
      ),
      "",
    );
    assert.equal(
      await page.$eval(
        '.frosh-security-timeline input[name="to"]',
        (input) => input.value,
      ),
      "",
    );
  }
  await setInput("clientIp", "192.0.2.99");
  assert.equal((await apply()).total, 0);
  await page.waitForFunction(() =>
    document
      .querySelector(".frosh-security-timeline")
      ?.textContent.includes("No activity matches these filters."),
  );
  const clear = listResponse();
  await page
    .locator(".frosh-security-timeline button ::-p-text(Clear filters)")
    .click();
  assert.equal((await clear).status(), 200);
  await page.waitForFunction(
    () =>
      !location.hash.includes("subjectId") &&
      !document.querySelector(".frosh-security-timeline__subject"),
  );
  assert.equal(
    await page.$eval(
      '.frosh-security-timeline input[name="clientIp"]',
      (input) => input.value,
    ),
    "",
  );
  for (const close of await page.$$(".sw-notifications .sw-alert__close"))
    await close.click();
  await page.waitForSelector(".sw-notifications", {
    hidden: true,
    timeout: 20000,
  });
  await page.screenshot({
    path: path.join(artifacts, "timeline.png"),
    fullPage: true,
  });
  assert.deepEqual(errors, []);
  console.log(
    JSON.stringify({
      result: "passed",
      checks: [
        "actor/action selects",
        "UTC preset and custom dates",
        "equivalent IPv6 filter",
        "pagination",
        "redacted details",
        "actual full filtered JSON download",
        "empty results",
        "clear scope and filters",
      ],
    }),
  );
} catch (error) {
  console.error(JSON.stringify({ stage, errors }));
  await page.screenshot({
    path: path.join(artifacts, "failure.png"),
    fullPage: true,
  });
  throw error;
} finally {
  if (created) {
    await promisify(execFile)(fixture, [id, "cleanup"], { timeout: 120000 });
    assert.equal((await api(`user/${id}`, "DELETE")).status, 204);
  }
  await browser.close();
}
