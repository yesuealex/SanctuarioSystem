
/*
 * SANCTUARIO FARM MAP - PLOT-LEVEL CROP LIFECYCLE GANTT
 * The database is the source of truth. This renderer reads the crop rows
 * already embedded by farmMap.php in dbCropsByPlot and draws one row per crop.
 */
(function () {
    "use strict";

    const DAY_MS = 24 * 60 * 60 * 1000;

    function parseDate(value) {
        if (!value) return null;

        if (value instanceof Date) {
            return Number.isNaN(value.getTime())
                ? null
                : new Date(value.getTime());
        }

        const m = String(value)
            .trim()
            .match(/^(\d{4})-(\d{2})-(\d{2})/);

        if (!m) return null;

        const d = new Date(
            Number(m[1]),
            Number(m[2]) - 1,
            Number(m[3])
        );

        return Number.isNaN(d.getTime()) ? null : d;
    }

    function fmt(value) {
        const d = parseDate(value);

        return d
            ? d.toLocaleDateString("en-US", {
                month: "short",
                day: "numeric",
                year: "numeric"
            })
            : "Not recorded";
    }

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function plotKeys(plot) {
        const raw = String(plot ?? "").trim();
        const upper = raw.toUpperCase();
        const letter = upper
            .replace(/^PLOT[_\s-]?/, "")
            .charAt(0);

        if (["A", "B", "C"].includes(letter)) {
            return [upper, letter, `PLOT_${letter}`];
        }

        if (upper === "HYDRO") return [upper, "HYDRO"];
        if (["TENT_HOUSE", "TENT-HOUSE", "TENT"].includes(upper)) {
            return ["TENT_HOUSE", upper];
        }

        return [upper, raw];
    }

    function plotLetter(plot) {
        const upper = String(plot ?? "").trim().toUpperCase();

        const match = upper.match(
            /(?:^|PLOT[_\s-]?)([ABC])(?:$|[_\s-]?\d*)/
        );

        return match ? match[1] : "";
    }

    // FIXED: Display the actual bed label, not the database bed_id.
    function bedLabel(row, plot, index) {
        const letter = plotLetter(plot);

        const candidates = [
            row?.bed_code,
            row?.nursery_bed_label,
            row?.transplanted_area
        ];

        for (const candidate of candidates) {
            const raw = String(candidate ?? "").trim();

            if (!raw) continue;

            const compact = raw
                .replace(/^BED[_\s-]*/i, "")
                .replace(/[\s_-]+/g, "")
                .toUpperCase();

            // Ignore values that identify only the plot.
            if (
                /^(?:PLOT)?[ABC]$/.test(compact) ||
                /^(NURSERY|MNO)$/.test(compact)
            ) {
                continue;
            }

            // Normalize labels such as "PLOT A BED 1" to "A1".
            const qualified = compact.match(
                /^(?:PLOT)?([ABC])(?:BED)?(\d+)$/
            );

            if (qualified) {
                return `${qualified[1]}${qualified[2]}`;
            }

            // Qualify a numeric bed label using its plot letter.
            if (/^\d+$/.test(compact) && letter) {
                return `${letter}${compact}`;
            }

            return compact;
        }

        // Use the actual bed number only if no bed code is available.
        const bedNumber = String(row?.bed_number ?? "").trim();

        if (/^\d+$/.test(bedNumber) && letter) {
            return `${letter}${bedNumber}`;
        }

        // Do not guess from row order; it could show the wrong bed.
        return "Bed not assigned";
    }

    function stage(row, today) {
        const status = String(row.status || "").toLowerCase();

        const sow = parseDate(row.date_sowing);
        const target = parseDate(row.target_transplant_date);
        const actual = parseDate(row.date_transplanted);
        const harvest = parseDate(row.expected_harvest);

        if (status === "harvested") {
            return ["Harvested", "harvested"];
        }

        if (status === "ready" || (harvest && today >= harvest)) {
            return ["Ready for Harvest", "ready"];
        }

        if (sow && today < sow) {
            return ["Scheduled", "scheduled"];
        }

        if (actual && today >= actual) {
            return ["Growing", "growing"];
        }

        if (target && today >= target) {
            return ["Ready for Transplant", "seedling"];
        }

        if (sow && today >= sow) {
            return ["Growing", "growing"];
        }

        if (status === "planted") {
            return ["Planted", "seedling"];
        }

        return ["No lifecycle data", "no-data"];
    }

    function rowData(row, today, axisStart, axisEnd) {
        const sow = parseDate(row.date_sowing);
        const target = parseDate(row.target_transplant_date);
        const actual = parseDate(row.date_transplanted);
        const harvest = parseDate(row.expected_harvest);

        const span = Math.max(
            1,
            Math.round((axisEnd - axisStart) / DAY_MS)
        );

        const pos = d => d
            ? Math.max(
                0,
                Math.min(
                    100,
                    ((d - axisStart) / DAY_MS) / span * 100
                )
            )
            : null;

        const bars = [];
        const startGrow = actual || target || sow;

        if (sow && target && target > sow) {
            bars.push([
                "sowing",
                pos(sow),
                Math.max(1, pos(target) - pos(sow))
            ]);
        }

        if (startGrow && harvest && harvest > startGrow) {
            bars.push([
                "growing",
                pos(startGrow),
                Math.max(1, pos(harvest) - pos(startGrow))
            ]);
        } else if (startGrow) {
            bars.push([
                "growing",
                pos(startGrow),
                Math.max(1, 100 - pos(startGrow))
            ]);
        }

        if (!bars.length && sow && harvest && harvest > sow) {
            bars.push([
                "growing",
                pos(sow),
                Math.max(1, pos(harvest) - pos(sow))
            ]);
        }

        return {
            sow,
            target,
            actual,
            harvest,
            pos,
            bars
        };
    }

    window.renderPlotLifecycleGantt = function (plot) {
        const host = document.getElementById(
            "plotCropLifecycleGantt"
        );

        if (!host) return;

        const cropSource =
            (typeof dbCropsByPlot !== "undefined")
                ? dbCropsByPlot
                : {};

        const rows = plotKeys(plot)
            .map(key => cropSource[key])
            .find(value => Array.isArray(value)) || [];

        if (!rows.length) {
            host.innerHTML = `
                <div class="plot-crop-lifecycle-empty">
                    <strong>Crop Lifecycle</strong>
                    <span>
                        No active crop records are assigned to this plot.
                    </span>
                </div>
            `;
            return;
        }

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const dates = [];

        rows.forEach(row => {
            [
                "date_sowing",
                "target_transplant_date",
                "date_transplanted",
                "expected_harvest"
            ].forEach(key => {
                const date = parseDate(row[key]);

                if (date) dates.push(date);
            });
        });

        if (!dates.length) {
            host.innerHTML = `
                <div class="plot-crop-lifecycle-empty">
                    <strong>Crop Lifecycle — ${esc(plot)}</strong>
                    <span>
                        ${rows.length} crop record${rows.length === 1 ? "" : "s"}
                        found, but lifecycle dates are not recorded yet.
                    </span>
                </div>
            `;
            return;
        }

        let min = new Date(
            Math.min(...dates.map(date => date.getTime()))
        );

        let max = new Date(
            Math.max(...dates.map(date => date.getTime()))
        );

        if (max <= min) {
            max = new Date(min.getTime() + 30 * DAY_MS);
        }

        const span = Math.max(
            1,
            Math.round((max - min) / DAY_MS)
        );

        const pad = Math.max(2, Math.ceil(span * 0.04));

        const axisStart = new Date(
            min.getTime() - pad * DAY_MS
        );

        const axisEnd = new Date(
            max.getTime() + pad * DAY_MS
        );

        const axisSpan = Math.max(
            1,
            Math.round((axisEnd - axisStart) / DAY_MS)
        );

        const position = date => Math.max(
            0,
            Math.min(
                100,
                ((date - axisStart) / DAY_MS) / axisSpan * 100
            )
        );

        const ticks = [0, 1, 2, 3, 4].map(index =>
            new Date(
                axisStart.getTime() +
                axisSpan * index / 4 * DAY_MS
            )
        );

        const todayPos = position(today);

        const todayLine =
            today >= axisStart && today <= axisEnd
                ? `<span class="plot-gantt-today" style="left:${todayPos}%"></span>`
                : "";

        const body = rows.map((row, index) => {
            const data = rowData(
                row,
                today,
                axisStart,
                axisEnd
            );

            const currentStage = stage(row, today);

            const name =
                row.crop_name ||
                row.crop ||
                row.name ||
                row.variety ||
                `Crop ${index + 1}`;

            const bed = bedLabel(row, plot, index);

            const bars = data.bars.map(bar => `
                <span
                    class="plot-gantt-bar ${bar[0]}"
                    style="left:${bar[1]}%;width:${bar[2]}%"
                ></span>
            `).join("");

            const marks = [
                ["sowing", data.sow, "Sowing"],
                ["transplant", data.target, "Target transplant"],
                ["actual", data.actual, "Actual transplant"],
                ["harvest", data.harvest, "Expected harvest"]
            ].map(item => {
                if (!item[1]) return "";

                return `
                    <span
                        class="plot-gantt-mark ${item[0]}"
                        style="left:${position(item[1])}%"
                        title="${esc(item[2] + ": " + fmt(item[1]))}"
                    ></span>
                `;
            }).join("");

            return `
                <div class="plot-gantt-row">
                    <div class="plot-gantt-name">
                        <strong>${esc(name)}</strong>
                        <small>${esc(bed)}</small>
                    </div>

                    <div class="plot-gantt-track">
                        ${todayLine}
                        ${bars}
                        ${marks}
                    </div>

                    <span class="plot-gantt-status ${currentStage[1]}">
                        ${esc(currentStage[0])}
                    </span>
                </div>
            `;
        }).join("");

        host.innerHTML = `
            <div class="plot-crop-lifecycle-head">
                <div>
                    <strong>Crop Lifecycle Gantt</strong>
                    <span>
                        All ${rows.length} active crop record${rows.length === 1 ? "" : "s"}
                        in this plot
                    </span>
                </div>

                <span class="plot-gantt-today-label">Today</span>
            </div>

            <div class="plot-gantt-axis">
                <span class="plot-gantt-axis-label first">
                    ${esc(fmt(ticks[0]))}
                </span>

                ${ticks.slice(1, -1).map(tick => `
                    <span
                        class="plot-gantt-axis-label"
                        style="left:${position(tick)}%"
                    >
                        ${esc(fmt(tick))}
                    </span>
                `).join("")}

                <span class="plot-gantt-axis-label last">
                    ${esc(fmt(ticks[4]))}
                </span>
            </div>

            <div class="plot-gantt-list">
                ${body}
            </div>

            <div class="plot-gantt-legend">
                <span>
                    <i class="sowing"></i>
                    Date of Sowing
                </span>

                <span>
                    <i class="growing"></i>
                    Growing
                </span>

                <span>
                    <i class="transplant"></i>
                    Date of Transplant
                </span>

                <span>
                    <i class="harvest"></i>
                    Date of Harvest
                </span>
            </div>
        `;
    };
})();

