<table style="width: 100%; margin-bottom: 20px; border-collapse: collapse; border-spacing: 0;">
  <tbody>
    <tr>
      <td style="width: 35%; border: 0; padding: 0; vertical-align: top;">
        <img src="?entryPoint=attachment&amp;id=694e19197dcc35fe0" style="display: block; max-width: 100%; max-height: 80px; width: auto;">
      </td>
      <td style="width: 65%; border: 0; padding: 0; vertical-align: top; text-align: right;">
        <p style="margin: 0; line-height: 1.5;">
          <span style="font-size: 22px; color: #2c3e50;"><strong>{{account.name}}</strong></span><br>
          <span style="font-size: 12px; color: #555;">
            {{account.billingAddressStreet}}<br>
            {{account.billingAddressPostalCode}} {{account.billingAddressCity}}, {{account.billingAddressCountry}}<br>
            <strong>Điện thoại:</strong> {{account.phoneNumber}}<br>
            <strong>Email:</strong> {{account.emailAddress}}
          </span>
        </p>
      </td>
    </tr>
  </tbody>
</table>

<div style="text-align: center; margin: 25px 0; padding: 15px; background-color: #f8f9fa; border-top: 3px solid #3498db; border-bottom: 3px solid #3498db;">
  <h1 style="margin: 0; padding: 0; font-size: 28px; color: #2c3e50; letter-spacing: 1px;">PHIẾU GIAO HÀNG</h1>
  <p style="margin: 5px 0 0 0; font-size: 14px; color: #7f8c8d;">DELIVERY NOTE</p>
</div>
      
<table class="no-border" style="margin-bottom: 20px; width: 100%; background-color: #f8f9fa; border-radius: 5px;">
  <tbody>
    <tr>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50; width: 20%;">Số phiếu:</td>
      <td style="border:0; padding: 8px 12px; width: 30%;">{{name}}</td>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50; width: 20%;">Ngày giao:</td>
      <td style="border:0; padding: 8px 12px; width: 30%;">{{deliveryDate}}</td>
    </tr>
    <tr>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50;">Tổng số kiện:</td>
      <td style="border:0; padding: 8px 12px;">{{totalPackages}}</td>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50;">Đã đến kho:</td>
      <td style="border:0; padding: 8px 12px;">{{createdAt}}</td>
    </tr>
    <tr>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50;">Tổng khối lượng:</td>
      <td style="border:0; padding: 8px 12px;"><strong>{{totalWeight}}</strong> kg</td>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50;">Loại dịch vụ:</td>
      <td style="border:0; padding: 8px 12px;">{{serviceType}}</td>
    </tr>
    <tr>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50;">Tổng thể tích:</td>
      <td style="border:0; padding: 8px 12px;"><strong>{{totalVolume}}</strong> m³</td>
      <td class="section-label" style="border:0; padding: 8px 12px; line-height: 1.4; font-weight: 600; color: #2c3e50;">Đơn giá giao hàng:</td>
      <td style="border:0; padding: 8px 12px;"><strong style="color: #e74c3c;">{{unitDeliveryPriceVnd}}</strong> ₫</td>
    </tr>
  </tbody>
</table>
    
<table style="border-collapse: collapse; width: 100%; border: 1px solid #ddd;">
  <thead>
    <tr style="background-color: #3498db; color: white;">
      <th class="center" style="width: 5%; padding: 10px; border: 1px solid #ddd;">STT</th>
      <th class="center" style="width: 18%; padding: 10px; border: 1px solid #ddd;">Mã vận đơn</th>
      <th class="center" style="width: 12%; padding: 10px; border: 1px solid #ddd;">Khối lượng (kg)</th>
      <th class="center" style="width: 12%; padding: 10px; border: 1px solid #ddd;">Thể tích (m³)</th>
      <th class="center" style="width: 15%; padding: 10px; border: 1px solid #ddd;">Phí giao hàng (₫)</th>
    </tr>
  </thead>
  <tbody class="order-items">
    {{#each parcels}}
    <tr style="{{#if @even}}background-color: #f8f9fa;{{/if}}">
      <td class="center" style="padding: 8px; border: 1px solid #ddd;">{{increment @index}}</td>
      <td style="padding: 8px; border: 1px solid #ddd;"><strong>{{name}}</strong></td>
      {{#if weight}}
      <td class="center" style="padding: 8px; border: 1px solid #ddd;">{{weight}}</td>
      {{else}}
      <td class="center" style="padding: 8px; border: 1px solid #ddd; color: #999;">—</td>
      {{/if}}
      {{#if volume}}
      <td class="center" style="padding: 8px; border: 1px solid #ddd;">{{volume}}</td>
      {{else}}
      <td class="center" style="padding: 8px; border: 1px solid #ddd; color: #999;">—</td>
      {{/if}}
      <td class="right" style="padding: 8px; border: 1px solid #ddd; font-weight: 600;">{{totalDeliveryPriceVnd}}</td>
    </tr>
    {{/each}}
    </tbody>
</table>

<table style="margin: 25px 0; width: 100%;">
  <tbody>
    <tr>
      <td style="width: 50%; border:0;"></td>
      <td style="border:0; vertical-align: top;">
        <table style="width: 100%; border: 2px solid #3498db; border-radius: 5px; background-color: #f8f9fa;">
          <tbody>
            <tr>
              <td class="section-label" style="border:0; padding: 10px 15px; line-height: 1.4; color: #2c3e50;">Tổng phí giao hàng:</td>
              <td class="right" style="border:0; padding: 10px 15px; line-height: 1.4; font-weight: 600;">{{totalDeliveryPriceVnd}} ₫</td>
            </tr>
            <tr style="background-color: #fff;">
              <td class="section-label" style="border:0; padding: 10px 15px; line-height: 1.4; color: #2c3e50;">Số tiền còn lại:</td>
              <td class="right" style="border:0; padding: 10px 15px; line-height: 1.4; font-weight: 600;">{{orderRemainingAmountVnd}} ₫</td>
            </tr>
            <tr>
              <td class="section-label" style="border:0; padding: 10px 15px; line-height: 1.4; color: #2c3e50;">Số tiền phụ thu:</td>
              <td class="right" style="border:0; padding: 10px 15px; line-height: 1.4; font-weight: 600; color: #e67e22;">{{additionalAmountVnd}} ₫</td>
            </tr>
            <tr style="background-color: #3498db; color: white; font-size: 16px;">
              <td class="section-label" style="border:0; padding: 12px 15px; line-height: 1.4; font-weight: bold;">TỔNG SỐ PHẢI TRẢ:</td>
              <td class="right" style="border:0; padding: 12px 15px; line-height: 1.4; font-weight: bold; font-size: 18px;">{{totalPayableAmountVnd}} ₫</td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
  </tbody>
</table>

{{#if notes}}
<div style="margin: 25px 0; padding: 15px; background-color: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
  <p style="margin: 0 0 8px 0;"><strong style="color: #856404;">📝 Ghi chú:</strong></p>
  <p style="margin: 0; color: #856404; line-height: 1.6;">{{notes}}</p>
</div>
{{/if}}

{{pagebreak}}

<div style="font-size: 11px; line-height: 1.5; margin: 15px 0; padding: 15px; background-color: #f8f9fa; border-left: 4px solid #e74c3c; border-radius: 4px;">
  <p style="margin: 0 0 10px 0;"><strong style="font-size: 13px; color: #c0392b;">⚠️ LƯU Ý QUAN TRỌNG:</strong></p>
  <ol style="margin: 0; padding-left: 20px;">
    <li style="margin-bottom: 8px;"><strong>Đối với khách nhận hàng trực tiếp tại kho:</strong>
    Vui lòng kiểm tra đầy đủ số lượng kiện hàng theo phiếu xuất kho trước khi ký nhận. Sau khi quý khách đã ký nhận, chúng tôi sẽ không tiếp nhận khiếu nại phát sinh liên quan đến hàng hóa.</li>
    <li style="margin-bottom: 8px;"><strong>Đối với hàng hóa gửi qua chuyển phát nhanh, nhà xe:</strong> Quý khách vui lòng quay video quá trình mở kiện hàng và kiểm tra mã vận đơn theo phiếu xuất kho. Trong trường hợp không có video đối soát, chúng tôi không thể xử lý các vấn đề phát sinh (thiếu, nhầm, hư hỏng...).</li>
    <li style="margin-bottom: 8px;"><strong>Thời gian tiếp nhận phản hồi:</strong> Mọi vấn đề liên quan đến đơn hàng vui lòng phản hồi trong vòng <strong style="color: #e74c3c;">24 giờ</strong> kể từ khi nhận hàng. Sau thời gian này, chúng tôi sẽ mặc định đơn hàng đã được giao đầy đủ và không có vấn đề gì phát sinh.</li>
  </ol>
  <p style="margin: 12px 0 0 0; font-style: italic; color: #555;">
    Rất mong nhận được sự phối hợp từ quý khách hàng để việc kiểm soát hàng hóa diễn ra thuận lợi và hiệu quả hơn. Trân trọng cảm ơn!
  </p>
</div>

<table class="no-border" style="margin: 50px 0 20px 0; width: 100%;">
  <tbody>
    <tr>
      <td style="border:0; width: 50%; text-align: center; vertical-align: top;">
        <p style="margin: 0;"><strong style="font-size: 13px;">NGƯỜI XUẤT KHO</strong></p>
        <p style="margin: 5px 0; font-size: 11px; font-style: italic; color: #666;">(Ký, ghi rõ họ tên)</p>
        <div style="height: 60px;"></div>
      </td>
      <td style="border:0; width: 50%; text-align: center; vertical-align: top;">
        <p style="margin: 0;"><strong style="font-size: 13px;">NGƯỜI NHẬN HÀNG</strong></p>
        <p style="margin: 5px 0; font-size: 11px; font-style: italic; color: #666;">(Ký, ghi rõ họ tên)</p>
        <div style="height: 60px;"></div>
      </td>
    </tr>
  </tbody>
</table>
